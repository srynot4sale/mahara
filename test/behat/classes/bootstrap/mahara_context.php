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
        $subcontexts = array('behat_hooks', 'behat_general');

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
     * Clicks link with specified id|title|alt|text.
     *
     * This overwrites the upstream step "@When /^(?:|I )follow "(?P<link>(?:[^"]|\\")*)"$/"
     * hence why this doc block is not in the usual format. If it was in the usual format then
     * Behat would find two conflicting regex and complain! So this is a bit of a hack :-)
     *
     * The reason for overwriting the upstream step is that it fails when there are two links
     * on the page with the same locator, but the first is hidden. It tried clicking the first
     * and fails, rather than defaulting to the first visible link (like this new step does).
     */
    public function clickLink($locator)
    {
        $locator = $this->fixStepArgument($locator);

        $links = $this->getSession()->getPage()->findAll('named', array(
            'link', $this->getSession()->getSelectorsHandler()->xpathLiteral($locator)
        ));

        if (!$links) {
            throw new ElementNotFoundException(
                $this->getSession(), 'link', 'id|title|alt|text', $locator
            );
        }

        foreach ($links as $link) {
            if ($link->isVisible()) {
                $link->click();
                return;
            }
        }

        throw new ExpectationException('Could not find a visible link with the "id|title|alt|text" of  "'.$locator.'"', $this->getSession());
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
     * Checks, that field with specified identifier exists on page.
     *
     * @Then /^(?:|I )should see the field "(?P<field>[^"]*)"$/
     */
    public function I_should_see_the_field($field)
    {
        $this->assertSession()->elementExists('named', array('field', $this->getSession()->getSelectorsHandler()->xpathLiteral($field)));
    }
}
