<?php

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use PrestaShop\Customerfield\Entity\CustomerCustomField;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Search\Filters\CustomerFilters;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class CustomerField extends Module
{
    public function __construct()
    {
        $this->name = 'customerfield';
        $this->tab = 'front_office_features';
        $this->version = '1.0';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;
        $this->bootstrap = 1;

        parent::__construct();

        $this->displayName = $this->trans('Custom customer field', [], 'Modules.Customerfield.Admin');
        $this->description = $this->trans('This module adds a new custom field to the customer');
        $this->ps_versions_compliancy = array('min' => '1.7.6.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return parent::install() and
            $this->installDB() and
            $this->registerHook('displayCustomerAccountForm') and
            $this->registerHook('additionalCustomerFormFields') and
            $this->registerHook('actionCustomerAccountAdd') and
            $this->registerHook('actionCustomerAccountUpdate') and
            $this->registerHook('actionAfterCreateCustomerFormHandler') and
            $this->registerHook('actionAfterUpdateCustomerFormHandler') and
            $this->registerHook('actionCustomerGridDefinitionModifier') and
            $this->registerHook('actionCustomerGridQueryBuilderModifier') and
            $this->registerHook('actionCustomerFormBuilderModifier');
    }

    protected function installDB()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'customer_custom_field` (
        `id_customer_custom_field` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_customer` INT(11) UNSIGNED NOT NULL,
        `shipping_preferences` VARCHAR(255) NULL,
        PRIMARY KEY(`id_customer_custom_field`, `id_customer`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';

        if (!Db::getInstance()->execute($sql))
            return false;

        return true;
    }

    /**
     * Hook allows to modify Customers query builder and add custom sql statements.
     *
     * @param array $params
     */
    public function hookActionCustomerGridQueryBuilderModifier(array $params)
    {
        /** @var QueryBuilder $searchQueryBuilder */
        $searchQueryBuilder = $params['search_query_builder'];

        /** @var CustomerFilters $searchCriteria */
        $searchCriteria = $params['search_criteria'];

        $searchQueryBuilder->addSelect(
            'ccf.`shipping_preferences`'
        );

        $searchQueryBuilder->leftJoin(
            'c',
            '`' . pSQL(_DB_PREFIX_) . 'customer_custom_field`',
            'ccf',
            'ccf.`id_customer` = c.`id_customer`'
        );

        if ('shipping_preferences' === $searchCriteria->getOrderBy()) {
            $searchQueryBuilder->orderBy('ccf.`shipping_preferences`', $searchCriteria->getOrderWay());
        }

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ('shipping_preferences' === $filterName) {
                $searchQueryBuilder->andWhere('ccf.`shipping_preferences` = :shipping_preferences');
                $searchQueryBuilder->setParameter('shipping_preferences', $filterValue);

                if (!$filterValue) {
                    $searchQueryBuilder->orWhere('ccf.`shipping_preferences` IS NULL');
                }
            }
        }
    }

    /**
     * Hook allows to modify Customers grid definition.
     * This hook is a right place to add/remove columns or actions (bulk, grid).
     *
     * @param array $params
     */
    public function hookActionCustomerGridDefinitionModifier(array $params)
    {
        /** @var GridDefinitionInterface $definition */
        $definition = $params['definition'];

        $translator = $this->getTranslator();

        $definition
            ->getColumns()
            ->addAfter(
                'optin',
                (new DataColumn('shipping_preferences'))
                    ->setName($translator->trans('Shipping Preferences', [], 'Modules.Customerfield.Admin'))
                    ->setOptions([
                        'field' => 'shipping_preferences'
                    ])
            )
        ;

        $definition->getFilters()->add(
            (new Filter('shipping_preferences', TextType::class))
                ->setAssociatedColumn('shipping_preferences')
        );
    }

    public function hookActionCustomerFormBuilderModifier(array $params)
    {
        /** @var FormBuilderInterface $formBuilder */
        $formBuilder = $params['form_builder'];
        $formBuilder->add('shipping_preferences', TextType::class, [
            'label' => $this->getTranslator()->trans('Shipping Preferences', [], 'Modules.Customerfield.Admin'),
            'required' => false,
        ]);

        $customerId = $params['id'];

        $params['data']['shipping_preferences'] = $this->getCustomerShippingPreferences($customerId);

        $formBuilder->setData($params['data']);
    }

    // Save/Update Hooks

    public function hookActionAfterUpdateCustomerFormHandler(array $params)
    {
        $customerId = $params['id'];
        /** @var array $customerFormData */
        $customerFormData = $params['form_data'];
        $shippingPreferences = $customerFormData['shipping_preferences'];

        $this->updateCustomerShippingPreferences($customerId, $shippingPreferences);
    }

    public function hookActionAfterCreateCustomerFormHandler(array $params)
    {
        $customerId = $params['id'];
        /** @var array $customerFormData */
        $customerFormData = $params['form_data'];
        $shippingPreferences = $customerFormData['shipping_preferences'];

        $this->updateCustomerShippingPreferences($customerId, $shippingPreferences);
    }

    private function updateCustomerShippingPreferences($customerId, $shippingPreferences)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->get('doctrine')->getManager();

        /** @var EntityRepository $repository */
        $repository = $this->get('doctrine')->getRepository(CustomerCustomField::class);

        /** @var CustomerCustomField $customerCustomField */
        $customerCustomField = $repository->findOneByCustomerId($customerId);
        if(is_null($customerCustomField) and $shippingPreferences){ // Create
            $customerCustomField = new CustomerCustomField();
            $customerCustomField->setCustomerId($customerId);
            $customerCustomField->setShippingPreferences($shippingPreferences);
            $entityManager->persist($customerCustomField); // No query yet
        } elseif(!is_null($customerCustomField) and is_null($shippingPreferences)) { // Clean
            $entityManager->remove($customerCustomField); // No query yet
        } elseif(!is_null($customerCustomField)) { // Update
            $customerCustomField->setShippingPreferences($shippingPreferences);
        }

        $entityManager->flush(); // Create, update or clean
    }

    private function getCustomerShippingPreferences($customerId)
    {
        /** @var EntityRepository $repository */
        $repository = $this->get('doctrine')->getRepository(CustomerCustomField::class);

        /** @var CustomerCustomField $customerCustomField */
        $customerCustomField = $repository->findOneByCustomerId($customerId);
        if(!is_null($customerCustomField)){
            return $customerCustomField->getShippingPreferences();
        }

        return '';
    }

    public function hookAdditionalCustomerFormFields($params)
    {
        $formField = new FormField();
        $formField->setName('shipping_preferences');
        $formField->setType('text');
        $formField->setLabel('Shipping preferences');
        $formField->setRequired(false);
        $formField->setValue($this->getCustomerShippingPreferences($this->context->customer->id));

        $last_key = array_key_last($params['fields']);
        $last = array_pop($params['fields']);

        $params['fields']['shipping_preferences'] = $formField;
        $params['fields'][$last_key] = $last;
    }

    public function hookActionCustomerAccountAdd($params)
    {
        $shippingPreferences = Tools::getValue('shipping_preferences');
        $customerId = $params['newCustomer']->id;
        $this->updateCustomerShippingPreferences($customerId, $shippingPreferences);
    }

    public function hookActionCustomerAccountUpdate($params)
    {
        $shippingPreferences = Tools::getValue('shipping_preferences');
        $customerId = $this->context->customer->id;
        $this->updateCustomerShippingPreferences($customerId, $shippingPreferences);
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }
}
