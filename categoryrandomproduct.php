<?php
/**
* 2007-2021 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Adapter\Category\CategoryProductSearchProvider;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;

class Categoryrandomproduct extends Module
{
    private $templateFile;

    public function __construct()
    {
        $this->name = 'categoryrandomproduct';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'CoolSoft-Web';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('categoryrandomproduct');
        $this->description = $this->l('Display on bottom category page extra product listing');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->templateFile = 'module:categoryrandomproduct/views/templates/hook/categoryrandomproduct.tpl';
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('CATEGORYRANDOMPRODUCT_NUMBER', 8);

        return parent::install() &&
            $this->registerHook('Header') &&
            $this->registerHook('actionFrontControllerSetMedia') &&
            $this->registerHook('actionCategoryRandomProduct');
    }

    public function uninstall()
    {
        Configuration::deleteByName('CATEGORYRANDOMPRODUCT_NUMBER');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitCategoryrandomproductModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitCategoryrandomproductModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Number of product'),
                        'name' => 'CATEGORYRANDOMPRODUCT_NUMBER',
                        'class' => 'fixed-width-xs',
                        'desc' => $this->l('Set the number of products that you would like to display on category bottom page'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'CATEGORYRANDOMPRODUCT_NUMBER' => Tools::getValue('CATEGORYRANDOMPRODUCT_NUMBER', (int) Configuration::get('CATEGORYRANDOMPRODUCT_NUMBER')),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    protected function getProducts($cat)
    {
        $category = new Category((int) $cat);

        $searchProvider = new CategoryProductSearchProvider(
            $this->context->getTranslator(),
            $category
        );

        $context = new ProductSearchContext($this->context);

        $query = new ProductSearchQuery();

        $nProducts = Configuration::get('CATEGORYRANDOMPRODUCT_NUMBER');
        if ($nProducts < 0) {
            $nProducts = 12;
        }

        $query
            ->setResultsPerPage($nProducts)
            ->setPage(1)
        ;

        $query->setSortOrder(SortOrder::random());

        $result = $searchProvider->runQuery(
            $context,
            $query
        );

        $assembler = new ProductAssembler($this->context);

        $presenterFactory = new ProductPresenterFactory($this->context);
        $presentationSettings = $presenterFactory->getPresentationSettings();
        $presenter = $presenterFactory->getPresenter();

        $products_for_template = [];

        foreach ($result->getProducts() as $rawProduct) {
            $products_for_template[] = $presenter->present(
                $presentationSettings,
                $assembler->assembleProduct($rawProduct),
                $this->context->language
            );
        }

        return $products_for_template;
    }

    public function hookActionFrontControllerSetMedia()
    {
        if ('category' === $this->context->controller->php_self) {
            $this->context->controller->registerStylesheet(
                'glidecore',
                $this->_path.'views/css/glide.core.min.css',
                [
                    'media' => 'all',
                    'priority' => 900,
                ]
            );
            $this->context->controller->registerStylesheet(
                'glidetheme',
                $this->_path.'views/css/glide.theme.min.css',
                [
                    'media' => 'all',
                    'priority' => 900,
                ]
            );
            $this->context->controller->registerStylesheet(
                'glidetheme',
                $this->_path.'views/css/glide.css',
                [
                    'media' => 'all',
                    'priority' => 900,
                ]
            );
            $this->context->controller->registerJavascript(
                'glidejs',
                $this->_path.'views/js/glide.min.js',
                [
                    'position' => 'bottom',
                    'priority' => 900,
                ]
            );
            $this->context->controller->registerJavascript(
                'glidejsconf',
                $this->_path.'views/js/glide.config.js',
                [
                    'position' => 'bottom',
                    'priority' => 900,
                ]
            );
        }

        // $this->context->controller->registerJavascript(
        //     'mymodule-javascript',
        //     $this->_path.'views/js/mymodule.js',
        //     [
        //         'position' => 'bottom',
        //         'priority' => 1000,
        //     ]
        // );
    }

    public function hookActionCategoryRandomProduct($params) {

        $products = $this->getProducts($params['category_id']);
        $this->smarty->assign('category_id_getted', $products);
        return $this->display(__FILE__, 'categoryrandomproduct.tpl');

    }

}
