<?php
namespace Selendroid\Driver;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Session;
use WebDriver\WebDriver;

class SelendroidDriver implements DriverInterface
{
    /**
     * The current Mink session
     * @var Session
     */
    private $session;

    /**
     * Whether the browser has been started
     * @var Boolean
     */
    private $started = false;

    /**
     * The WebDriver instance
     * @var WebDriver
     */
    private $webDriver;

    /**
     * @var string
     */
    private $browserName;

    /**
     * @var array
     */
    private $desiredCapabilities;

    /**
     * The WebDriverSession instance
     * @var \WebDriver\Session
     */
    private $wdSession;

    /**
     * Instantiates the driver.
     *
     * @param string $browserName Browser name
     * @param array $desiredCapabilities The desired capabilities
     * @param string $wdHost The WebDriver host
     */
    public function __construct(
        $browserName = 'android',
        $desiredCapabilities = null,
        $wdHost = 'http://localhost:4444/wd/hub'
    ) {
        $this->setBrowserName($browserName);
        $this->setDesiredCapabilities($desiredCapabilities);
        $this->setWebDriver(new WebDriver($wdHost));
    }

    /**
     * Sets the browser name
     *
     * @param string $browserName the name of the browser to start, default is 'android'
     */
    protected function setBrowserName($browserName = 'android')
    {
        $this->browserName = $browserName;
    }

    /**
     * Sets the desired capabilities - called on construction.  If null is provided, will set the
     * defaults as desired.
     *
     * See http://code.google.com/p/selenium/wiki/DesiredCapabilities
     *
     * @param   array $desiredCapabilities an array of capabilities to pass on to the WebDriver server
     */
    public function setDesiredCapabilities($desiredCapabilities = null)
    {
        if (null === $desiredCapabilities) {
            $desiredCapabilities = self::getDefaultCapabilities();
        }

        $this->desiredCapabilities = $desiredCapabilities;
    }

    /**
     * Sets the WebDriver instance
     *
     * @param WebDriver $webDriver An instance of the WebDriver class
     */
    public function setWebDriver(WebDriver $webDriver)
    {
        $this->webDriver = $webDriver;
    }

    /**
     * Gets the WebDriverSession instance
     *
     * @return \WebDriver\Session
     */
    public function getWebDriverSession()
    {
        return $this->wdSession;
    }

    /**
     * Returns the default capabilities
     *
     * @return array
     */
    public static function getDefaultCapabilities()
    {
        return array(
            'browserName' => 'android',
            'emulator' => 'True',
            'platformVersion' => '19',
            'locale' => 'en_US',
            'screenSize' => '720x1280',
            'display' => '1',
            'preSessionAdbCommands' => '',
        );
    }

    /**
     * Makes sure that the Syn event library has been injected into the current page,
     * and return $this for a fluid interface, * $this->withSyn()->executeJsOnXpath($xpath, $script);
     *
     * @return SelendroidDriver
     */
    protected function withSyn()
    {
        $hasSyn = $this->wdSession->execute(
            array(
                'script' => 'return typeof window["Syn"]!=="undefined"',
                'args' => array()
            )
        );

        if (!$hasSyn) {
            $synJs = file_get_contents(__DIR__ . '/syn.js');
            $this->wdSession->execute(
                array(
                    'script' => $synJs,
                    'args' => array()
                )
            );
        }

        return $this;
    }

    /**
     * Creates some options for key events
     *
     * @param  string $char the character or code
     * @param  string $modifier =null one of 'shift', 'alt', 'ctrl' or 'meta'
     *
     * @return string a json encoded options array for Syn
     */
    protected static function charToOptions($char, $modifier = null)
    {
        $ord = ord($char);
        if (is_numeric($char)) {
            $ord = $char;
        }

        $options = array(
            'keyCode' => $ord,
            'charCode' => $ord
        );

        if ($modifier) {
            $options[$modifier . 'Key'] = 1;
        }

        return json_encode($options);
    }

    /**
     * Executes JS on a given element - pass in a js script string and {{ELEMENT}} will
     * be replaced with a reference to the result of the $xpath query
     *
     * @example $this->executeJsOnXpath($xpath, 'return {{ELEMENT}}.childNodes.length');
     *
     * @param  string $xpath the xpath to search with
     * @param  string $script the script to execute
     * @param  Boolean $sync whether to run the script synchronously (default is TRUE)
     *
     * @return mixed
     */
    protected function executeJsOnXpath($xpath, $script, $sync = true)
    {
        $element = $this->wdSession->element('xpath', $xpath);
        $elementID = $element->getID();
        $subscript = "arguments[0]";

        $script = str_replace('{{ELEMENT}}', $subscript, $script);
        $execute = ($sync) ? 'execute' : 'execute_async';

        return $this->wdSession->$execute(
            array(
                'script' => $script,
                'args' => array(array('ELEMENT' => $elementID))
            )
        );
    }


    /**
     * @see Behat\Mink\Driver\DriverInterface::setSession()
     *
     * @param Session $session
     */
    public function setSession(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Starts driver.
     */
    public function start()
    {
        $this->wdSession = $this->webDriver->session($this->browserName, $this->desiredCapabilities);
        if (!$this->wdSession) {
            throw new DriverException('Could not connect to a Selendroid / WebDriver server');
        }
        $this->started = true;
    }

    /**
     * Checks whether driver is started.
     *
     * @return  Boolean
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * Stops driver.
     */
    public function stop()
    {
        if (!$this->wdSession) {
            throw new DriverException('Could not connect to a Selendroid / WebDriver server');
        }

        $this->started = false;
        try {
            $this->wdSession->close();
        } catch (\Exception $e) {
            throw new DriverException('Could not close connection');
        }
    }

    /**
     * Resets driver.
     */
    public function reset()
    {
        $this->wdSession->deleteAllCookies();
    }

    /**
     * Visit specified URL.
     *
     * @param   string $url url of the page
     */
    public function visit($url)
    {
        $this->wdSession->open($url);
    }

    /**
     * Returns current URL address.
     *
     * @return  string
     */
    public function getCurrentUrl()
    {
        return $this->wdSession->getUrl();
    }

    /**
     * Reloads current page.
     */
    public function reload()
    {
        $this->wdSession->refresh();
    }

    /**
     * Moves browser forward 1 page.
     */
    public function forward()
    {
        $this->wdSession->forward();
    }

    /**
     * Moves browser backward 1 page.
     */
    public function back()
    {
        $this->wdSession->back();
    }

    /**
     * Switches to specific browser window.
     *
     * @param string $name window name (null for switching back to main window)
     */
    public function switchToWindow($name = null)
    {
        $this->wdSession->focusWindow($name ? $name : '');
    }

    /**
     * Switches to specific iFrame.
     *
     * @param string $name iframe name (null for switching back)
     */
    public function switchToIFrame($name = null)
    {
        $this->wdSession->frame(array('id' => $name));
    }

    /**
     * Sets HTTP Basic authentication parameters
     *
     * @param   string|false $user user name or false to disable authentication
     * @param   string $password password
     *
     * @throws UnsupportedDriverActionException
     */
    public function setBasicAuth($user, $password)
    {
        throw new UnsupportedDriverActionException('Basic Auth is not supported by %s', $this);
    }

    /**
     * Sets specific request header on client.
     *
     * @param   string $name
     * @param   string $value
     *
     * @throws UnsupportedDriverActionException
     */
    public function setRequestHeader($name, $value)
    {
        throw new UnsupportedDriverActionException('Request header is not supported by %s', $this);
    }

    /**
     * Returns last response headers.
     *
     * @throws UnsupportedDriverActionException
     * @return  array
     */
    public function getResponseHeaders()
    {
        throw new UnsupportedDriverActionException('Response header is not supported by %s', $this);
    }

    /**
     * Sets cookie.
     *
     * @param   string $name
     * @param   string $value
     */
    public function setCookie($name, $value = null)
    {
        if (null === $value) {
            $this->wdSession->deleteCookie($name);

            return;
        }

        $cookieArray = array(
            'name' => $name,
            'value' => (string) $value,
            'secure' => false, // thanks, chibimagic!
        );

        $this->wdSession->setCookie($cookieArray);
    }

    /**
     * Returns cookie by name.
     *
     * @param   string $name
     *
     * @return  string|null
     */
    public function getCookie($name)
    {
        $cookies = $this->wdSession->getAllCookies();
        foreach ($cookies as $cookie) {
            if ($cookie['name'] === $name) {
                return urldecode($cookie['value']);
            }
        }

        return null;
    }

    /**
     * Returns last response status code.
     *
     * @throws UnsupportedDriverActionException
     * @return  integer
     */
    public function getStatusCode()
    {
        throw new UnsupportedDriverActionException('Status code is not supported by %s', $this);
    }

    /**
     * Returns last response content.
     *
     * @return  string
     */
    public function getContent()
    {
        return $this->wdSession->source();
    }

    /**
     * Capture a screen shot of the current window.
     *
     * @return  string  screen shot of MIME type image/* depending
     *   on driver (e.g., image/png, image/jpeg)
     */
    public function getScreenShot()
    {
        return base64_decode($this->wdSession->screenshot());
    }

    /**
     * Finds elements with specified XPath query.
     *
     * @param   string $xpath
     *
     * @return  NodeElement[]
     */
    public function find($xpath)
    {
        $nodes = $this->wdSession->elements('xpath', $xpath);

        $elements = array();
        foreach ($nodes as $i => $node) {
            $elements[] = new NodeElement(sprintf('(%s)[%d]', $xpath, $i + 1), $this->session);
        }

        return $elements;
    }

    /**
     * Returns element's tag name by it's XPath query.
     *
     * @param   string $xpath
     *
     * @return  string
     */
    public function getTagName($xpath)
    {
        return $this->wdSession->element('xpath', $xpath)->name();
    }

    /**
     * Returns element's text by it's XPath query.
     *
     * @param   string $xpath
     *
     * @return  string
     */
    public function getText($xpath)
    {
        $node = $this->wdSession->element('xpath', $xpath);
        $text = $node->text();
        $text = (string) str_replace(array("\r", "\r\n", "\n"), ' ', $text);

        return $text;
    }

    /**
     * Returns element's html by it's XPath query.
     *
     * @param   string $xpath
     *
     * @return  string
     */
    public function getHtml($xpath)
    {
        return $this->executeJsOnXpath($xpath, 'return {{ELEMENT}}.innerHTML;');
    }

    /**
     * Returns element's attribute by it's XPath query.
     *
     * @param   string $xpath
     *
     * @param string $name
     *
     * @return  string|null
     */
    public function getAttribute($xpath, $name)
    {
        $attribute = $this->wdSession->element('xpath', $xpath)->attribute($name);
        if ('' !== $attribute) {
            return $attribute;
        }

        return null;
    }

    /**
     * Returns element's value by it's XPath query.
     *
     * @param   string $xpath
     *
     * @return  mixed
     */
    public function getValue($xpath)
    {
        $script = <<<JS
var node = {{ELEMENT}},
    tagName = node.tagName;

if (tagName == "INPUT" || "TEXTAREA" == tagName) {
    var type = node.getAttribute('type');
    if (type == "checkbox") {
        value = "boolean:" + node.checked;
    } else if (type == "radio") {
        var name = node.getAttribute('name');
        if (name) {
            var fields = window.document.getElementsByName(name);
            var i, l = fields.length;
            for (i = 0; i < l; i++) {
                var field = fields.item(i);
                if (field.checked) {
                    value = "string:" + field.value;
                }
            }
        }
    } else {
        value = "string:" + node.value;
    }
} else if (tagName == "SELECT") {
    if (node.getAttribute('multiple')) {
        options = [];
        for (var i = 0; i < node.options.length; i++) {
            if (node.options[ i ].selected) {
                options.push(node.options[ i ].value);
            }
        }
        value = "array:" + options.join(',');
    } else {
        var idx = node.selectedIndex;
        if (idx >= 0) {
            value = "string:" + node.options.item(idx).value;
        } else {
            value = null;
        }
    }
} else {
    attributeValue = node.getAttribute('value');
    if (attributeValue != null) {
        value = "string:" + attributeValue;
    } else if (node.value) {
        value = "string:" + node.value;
    } else {
        return null;
    }
}

return value;
JS;

        $value = $this->executeJsOnXpath($xpath, $script);
        if ($value) {
            if (preg_match('/^string:(.*)$/ms', $value, $vars)) {
                return $vars[1];
            }
            if (preg_match('/^boolean:(.*)$/', $value, $vars)) {
                return 'true' === strtolower($vars[1]);
            }
            if (preg_match('/^array:(.*)$/', $value, $vars)) {
                if ('' === trim($vars[1])) {
                    return array();
                }

                return explode(',', $vars[1]);
            }
        }

        return null;
    }

    /**
     * Sets element's value by it's XPath query.
     *
     * @param   string $xpath
     * @param   string $value
     */
    public function setValue($xpath, $value)
    {
        $element = $this->wdSession->element('xpath', $xpath);
        $elementName = strtolower($element->name());
        if (
            $elementName == 'textarea' ||
            ($elementName == 'input' && strtolower($element->attribute('type')) != 'file')
        ) {
            $element->clear();
        }

        $element->value(array('value' => array($value)));
    }

    /**
     * Checks checkbox by it's XPath query.
     *
     * @param   string $xpath
     */
    public function check($xpath)
    {
        $this->executeJsOnXpath($xpath, '{{ELEMENT}}.checked = true');
    }

    /**
     * Unchecks checkbox by it's XPath query.
     *
     * @param   string $xpath
     */
    public function unCheck($xpath)
    {
        $this->executeJsOnXpath($xpath, '{{ELEMENT}}.checked = false');
    }

    /**
     * Checks whether checkbox checked located by it's XPath query.
     *
     * @param   string $xpath
     *
     * @return  Boolean
     */
    public function isChecked($xpath)
    {
        return $this->wdSession->element('xpath', $xpath)->selected();
    }

    /**
     * Selects option from select field located by it's XPath query.
     *
     * @param   string $xpath
     * @param   string $value
     * @param   Boolean $multiple
     */
    public function selectOption($xpath, $value, $multiple = false)
    {
        $valueEscaped = str_replace('"', '\"', $value);
        $multipleJS = $multiple ? 'true' : 'false';

        $script = <<<JS
// Function to triger an event. Cross-browser compliant. See http://stackoverflow.com/a/2490876/135494
var triggerEvent = function (element, eventName) {
    var event;
    if (document.createEvent) {
        event = document.createEvent("HTMLEvents");
        event.initEvent(eventName, true, true);
    } else {
        event = document.createEventObject();
        event.eventType = eventName;
    }

    event.eventName = eventName;

    if (document.createEvent) {
        element.dispatchEvent(event);
    } else {
        element.fireEvent("on" + event.eventType, event);
    }
}

var node = {{ELEMENT}}
if (node.tagName == 'SELECT') {
    var i, l = node.length;
    for (i = 0; i < l; i++) {
        if (node[i].value == "$valueEscaped") {
            node[i].selected = true;
        } else if (!$multipleJS) {
            node[i].selected = false;
        }
    }
    triggerEvent(node, 'change');

} else {
    var nodes = window.document.getElementsByName(node.getAttribute('name'));
    var i, l = nodes.length;
    for (i = 0; i < l; i++) {
        if (nodes[i].getAttribute('value') == "$valueEscaped") {
            node.checked = true;
        }
    }
}
JS;


        $this->executeJsOnXpath($xpath, $script);
    }

    /**
     * Clicks button or link located by it's XPath query.
     *
     * @param   string $xpath
     */
    public function click($xpath)
    {
        $this->wdSession->element('xpath', $xpath)->click('');
    }

    /**
     * Double-clicks button or link located by it's XPath query.
     *
     * @param   string $xpath
     */
    public function doubleClick($xpath)
    {
        $script = 'Syn.dblclick({{ELEMENT}})';
        $this->withSyn()->executeJsOnXpath($xpath, $script);
    }

    /**
     * Right-clicks button or link located by it's XPath query.
     *
     * @param   string $xpath
     */
    public function rightClick($xpath)
    {
        $script = 'Syn.rightClick({{ELEMENT}})';
        $this->withSyn()->executeJsOnXpath($xpath, $script);
    }

    /**
     * Attaches file path to file field located by it's XPath query.
     *
     * @param   string $xpath
     * @param   string $path
     */
    public function attachFile($xpath, $path)
    {
        $this->wdSession->element('xpath', $xpath)->value(array('value' => str_split($path)));
    }

    /**
     * Checks whether element visible located by it's XPath query.
     *
     * @param   string $xpath
     *
     * @return  Boolean
     */
    public function isVisible($xpath)
    {
        return $this->wdSession->element('xpath', $xpath)->displayed();
    }

    /**
     * Simulates a mouse over on the element.
     *
     * @param   string $xpath
     */
    public function mouseOver($xpath)
    {
        $script = 'Syn.trigger("mouseover", {}, {{ELEMENT}})';
        $this->withSyn()->executeJsOnXpath($xpath, $script);
    }

    /**
     * Brings focus to element.
     *
     * @param   string $xpath
     */
    public function focus($xpath)
    {
        $script = 'Syn.trigger("focus", {}, {{ELEMENT}})';
        $this->withSyn()->executeJsOnXpath($xpath, $script);
    }

    /**
     * Removes focus from element.
     *
     * @param   string $xpath
     */
    public function blur($xpath)
    {
        $script = 'Syn.trigger("blur", {}, {{ELEMENT}})';
        $this->withSyn()->executeJsOnXpath($xpath, $script);
    }

    /**
     * Presses specific keyboard key.
     *
     * @param   string $xpath
     * @param   mixed $char could be either char ('b') or char-code (98)
     * @param   string $modifier keyboard modifier (could be 'ctrl', 'alt', 'shift' or 'meta')
     */
    public function keyPress($xpath, $char, $modifier = null)
    {
        $options = self::charToOptions($char, $modifier);
        $script = "Syn.trigger('keypress', $options, {{ELEMENT}})";
        $this->withSyn()->executeJsOnXpath($xpath, $script);
    }

    /**
     * Pressed down specific keyboard key.
     *
     * @param   string $xpath
     * @param   mixed $char could be either char ('b') or char-code (98)
     * @param   string $modifier keyboard modifier (could be 'ctrl', 'alt', 'shift' or 'meta')
     */
    public function keyDown($xpath, $char, $modifier = null)
    {
        $options = self::charToOptions($char, $modifier);
        $script = "Syn.trigger('keydown', $options, {{ELEMENT}})";
        $this->withSyn()->executeJsOnXpath($xpath, $script);
    }

    /**
     * Pressed up specific keyboard key.
     *
     * @param   string $xpath
     * @param   mixed $char could be either char ('b') or char-code (98)
     * @param   string $modifier keyboard modifier (could be 'ctrl', 'alt', 'shift' or 'meta')
     */
    public function keyUp($xpath, $char, $modifier = null)
    {
        $options = self::charToOptions($char, $modifier);
        $script = "Syn.trigger('keyup', $options, {{ELEMENT}})";
        $this->withSyn()->executeJsOnXpath($xpath, $script);
    }


    /**
     * Drag one element onto another.
     *
     * @param   string $sourceXpath
     * @param   string $destinationXpath
     */
    public function dragTo($sourceXpath, $destinationXpath)
    {
        $source = $this->wdSession->element('xpath', $sourceXpath);
        $destination = $this->wdSession->element('xpath', $destinationXpath);

        $this->wdSession->moveto(
            array(
                'element' => $source->getID()
            )
        );

        $script = <<<JS
(function (element) {
    var event = document.createEvent("HTMLEvents");

    event.initEvent("dragstart", true, true);
    event.dataTransfer = {};

    element.dispatchEvent(event);
}({{ELEMENT}}));
JS;
        $this->withSyn()->executeJsOnXpath($sourceXpath, $script);

        $this->wdSession->buttondown();
        $this->wdSession->moveto(
            array(
                'element' => $destination->getID()
            )
        );
        $this->wdSession->buttonup();

        $script = <<<JS
(function (element) {
    var event = document.createEvent("HTMLEvents");

    event.initEvent("drop", true, true);
    event.dataTransfer = {};

    element.dispatchEvent(event);
}({{ELEMENT}}));
JS;
        $this->withSyn()->executeJsOnXpath($destinationXpath, $script);
    }

    /**
     * Executes JS script.
     *
     * @param   string $script
     */
    public function executeScript($script)
    {
        $this->wdSession->execute(array('script' => $script, 'args' => array()));
    }

    /**
     * Evaluates JS script.
     *
     * @param   string $script
     *
     * @return  mixed           script return value
     */
    public function evaluateScript($script)
    {
        return $this->wdSession->execute(array('script' => $script, 'args' => array()));
    }

    /**
     * Waits some time or until JS condition turns true.
     *
     * @param   integer $time time in milliseconds
     * @param   string $condition JS condition
     */
    public function wait($time, $condition)
    {
        $script = "return $condition;";
        $start = 1000 * microtime(true);
        $end = $start + $time;
        while (1000 * microtime(true) < $end && !$this->wdSession->execute(array('script' => $script, 'args' => array()))) {
            sleep(0.1);
        }
    }

    /**
     * Set the dimensions of the window.
     *
     * @param integer $width set the window width, measured in pixels
     * @param integer $height set the window height, measured in pixels
     * @param string $name window name (null for the main window)
     */
    public function resizeWindow($width, $height, $name = null)
    {
        return $this->wdSession->window($name ? $name : '')->postSize(array('width' => $width, 'height' => $height));
    }
}
