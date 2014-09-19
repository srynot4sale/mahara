<?php
/**
 * @package    mahara
 * @subpackage test/behat
 * @author     Son Nguyen, Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 * @copyright  portions from Moodle Behat, 2013 David Monllaó
 *
 */

/**
 * Mahara context class.
 *
 */

use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\MinkContext;

class mahara_context extends MinkContext {
    protected static $defined_named_selector_types = array(
        'link', 'button', 'link_or_button', 
        'fieldset', 'field', 
        'select', 'checkbox', 'radio', 'file', 'optgroup', 'option',
        'table', 'content', 
    );

    /**
     * The timeout in miliseconds for each Behat step (load page, wait for an element to load...).
     */
    const TIMEOUT = 3000;

    /**
     * The JS code to check that the page is ready.
     */
    const PAGE_READY_JS = 'false';

    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     *
     * @param array $parameters context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters) {
        // Initialize all sub context here
        $subcontexts = array('behat_hooks', );

        foreach ($subcontexts as $classname) {
            $path = dirname(dirname(__FILE__)) . '/' . $classname . '.php';
            if (file_exists($path)) {
                require_once($path);
                $this->useContext($classname, new $classname());
            }
        }
    }

    /**
     * Login as mahara user
     *
     * @Given /^I am logged in as the user "([^"]*)" with password "([^"]*)"$/
     */
    public function i_am_logged_in_as($username, $password) {
        $element = $this->getSession()->getPage();
        $this->getSession()->visit($this->locatePath('/'));
        $element->fillField('login_username', $username);
        $element->fillField('login_password', $password);
        $submit = $element->findButton('Login');
        $submit->click();
    }

    /**
     * Opens user profile.
     *
     * @Given /^I am on user profile$/
     */
    public function i_am_on_user_profile() {
        $this->getSession()->visit($this->locate_path('/artefact/internal/index.php'));
    }

    /**
     * Opens admin page.
     *
     * @Given /^I am on admin page$/
     */
    public function i_am_on_admin_page() {
        $this->getSession()->visit($this->locate_path('/admin/index.php'));
    }

    /**
     * Click on the element and wait.
     *
     * @When /^I click on "(?P<element>[^"]*)" "(?P<selector>[^"]*)"$/
     * @param string $element Element we look for
     */
    public function i_click_on($element, $selector) {

        list ($selector, $locator) = $this->transform_locator($selector, $element);
        $nodes = $this->getSession()->getPage()->findAll($selector, $locator);
        // Find the first visible node
        if (count($nodes) > 0) {
            foreach ($nodes as $node) {
                if ($this->can_run_javascript() && $node->isVisible()) {
                    $node->click();
                    return;
                }
            }
        }
    }

    protected function transform_locator($selector, $element) {
        if ($selector == 'named') {
            if (preg_match('/^(\w+)=(.+)$/', $element, $matches) === 1) {
                // Check for supported named selectors
                if (in_array($matches[1], $this::$defined_named_selector_types)) {
                    $element = array($matches[1], $this->getSession()->getSelectorsHandler()->xpathLiteral($matches[2]));
                }
                else {
                    throw new ExpectationException('The "' . $selectortype . '" selector type does not exist', $this->getSession());
                }
            }
            else {
                $element = array('content', $this->getSession()->getSelectorsHandler()->xpathLiteral($element));
            }
        }
        if ($selector == 'xpath') {
            $element = preg_replace('/&quot;/', '"', $element);
        }
        return array($selector, $element);
    }

    /**
     * Returns whether the scenario is running in a browser that can run Javascript or not.
     *
     * @return boolean
     */
    protected function can_run_javascript() {
        return get_class($this->getSession()->getDriver()) !== 'Behat\Mink\Driver\GoutteDriver';
    }

    /**
     * Click on the link with specified id|title|value and wait.
     *
     * @When /^I click on the link "(?P<link>[^"]*)"$/
     * @param string $element Element we look for
     */
    public function i_click_on_the_link($link) {

        $page = $this->getSession()->getPage();
        $node = $page->find('named', array('link', $this->getSession()->getSelectorsHandler()->xpathLiteral($link)));
        if ($node) {
            $this->ensure_node_is_visible($node);
            $node->click();
        }
    }

    /**
     * Checks, that the specified element is visible. Only available in tests using Javascript.
     *
     * @Then /^I should see the element "(?P<element>(?:[^"]|\\")*)" "(?P<selectortype>(?:[^"]|\\")*)"$/
     * @param string $element
     * @param string $selectortype
     * @return void
     */
    public function I_should_see_the_element($element, $selectortype) {

        list ($selector, $locator) = $this->transform_locator($selectortype, $element);
        $node = $this->getSession()->getPage()->find($selector, $locator);

        if (null === $node) {
            throw new ElementNotFoundException($this->getSession(), 'element', $selector, $locator);
        }

        if (!$this->can_run_javascript()) {
            return;
        }

        if (!$node->isVisible()) {
            throw new ExpectationException('The element "' . $node->getTagName() . '" "' . $node->getXpath() . '" is not visible', $this->getSession());
        }
    }

    /**
     * Checks, that the specified element with given value is visible. Only available in tests using Javascript.
     *
     * @Then /^I should see "(?P<value>(?:[^"]|\\")*)" in the element "(?P<element>(?:[^"]|\\")*)" "(?P<selectortype>(?:[^"]|\\")*)"$/
     * @param string $element
     * @param string $selectortype
     * @param string $value
     * @return void
     */
    public function I_should_see_in_the_element($value, $element, $selectortype) {

        list ($selector, $locator) = $this->transform_locator($selectortype, $element);
        $page = $this->getSession()->getPage();
        $node = $page->find($selector, $locator);

        if (null === $node) {
            throw new ElementNotFoundException($this->getSession(), 'element', $selector, $locator);
        }

        if ($this->can_run_javascript() && !$node->isVisible()) {
            throw new ExpectationException('The element "' . $element . '" is not visible', $this->getSession());
        }

        // $value can be a regexp pattern with format: "regexp:<PATTERN>"
        if (preg_match('/^regexp:(.+)$/', $value, $matches) === 1) {
            $pat = '`' . $matches[1] . '`';
            if (preg_match($pat, $node->getValue()) !== 1
                && preg_match($pat, $node->getText()) !== 1
                && preg_match($pat, $node->getHtml()) !== 1) {
                throw new ElementNotFoundException($this->getSession(), 'element with regexp: "' . $pat . '"', $selector, $locator);
            }
        }
        else if ($node->getValue() !== $value
                && $node->getText() !== $value) {
            throw new ElementNotFoundException($this->getSession(), 'element with value "' . $value . '"', $selector, $locator);
        }
    }

    /**
     * Checks, that the specified element without given value is visible. Only available in tests using Javascript.
     *
     * @Then /^I should see not "(?P<value>(?:[^"]|\\")*)" in the element "(?P<element>(?:[^"]|\\")*)" "(?P<selectortype>(?:[^"]|\\")*)"$/
     * @param string $element
     * @param string $selectortype
     * @param string $value
     * @return void
     */
    public function I_should_see_not_in_the_element($value, $element, $selectortype) {

        list ($selector, $locator) = $this->transform_locator($selectortype, $element);
        $page = $this->getSession()->getPage();
        $node = $page->find($selector, $locator);

        if (null === $node) {
            throw new ElementNotFoundException($this->getSession(), 'element', $selector, $locator);
        }

        if ($this->can_run_javascript() && !$node->isVisible()) {
            throw new ExpectationException('The element "' . $element . '" is not visible', $this->getSession());
        }

        // $value can be a regexp pattern with format: "regexp:<PATTERN>"
        if (preg_match('/^regexp:(.+)$/', $value, $matches) === 1) {
            $pat = '`' . $matches[1] . '`';
            if (preg_match($pat, $node->getValue()) === 1
                || preg_match($pat, $node->getText()) === 1
                || preg_match($pat, $node->getHtml()) === 1) {
                throw new ExpectationException('The element "' . $element . '" with regexp: "' . $pat . '" is found', $this->getSession());
            }
        }
        else if ($node->getValue() === $value
                || $node->getText() === $value) {
            throw new ExpectationException('The element "' . $element . '" with value "' . $value . '" is found', $this->getSession());
        }
    }

    /**
     * Checks, that field with specified identifier exists on page.
     *
     * @Then /^(?:|I )should see the field "(?P<field>[^"]*)"$/
     */
    public function I_should_see_the_field($field)
    {
        $this->assertSession()->elementExists('named', array('field', $this->getSession()->getSelectorsHandler()->xpathLiteral($field)));
    }

    /**
     * Fills in an element.
     *
     * @When /^(?:|I )fill in "(?P<element>(?:[^"]|\\")*)" "(?P<selectortype>(?:[^"]|\\")*)" with "(?P<value>(?:[^"]|\\")*)"$/
     */
    public function fillField($element, $selectortype, $value)
    {
        list ($selector, $locator) = $this->transform_locator($selectortype, $element);
        $node = $this->getSession()->getPage()->find($selector, $locator);

        if (null === $node) {
            throw new ElementNotFoundException($this->getSession(), 'element', $selector, $locator);
        }

        if ($this->can_run_javascript() && !$node->isVisible()) {
            throw new ExpectationException('The element "' . $element . '" is not visible', $this->getSession());
        }

        $value = $this->fixStepArgument($value);
        $node->setValue($value);
    }

}
