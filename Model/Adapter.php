<?php

namespace Stripeofficial\CreditCards\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\ScopeInterface;
use Stripeofficial\Core\Model\DataProvider;

class Adapter extends \Magento\Payment\Model\Method\Adapter
{
    public function getConfigPaymentAction()
    {
        $objectManager = ObjectManager::getInstance();
        /** @var DataProvider $dataProvider */
        $dataProvider = $objectManager->get(DataProvider::class);

        if ($dataProvider->getStripeContext() === 'webhook') {
            return parent::getConfigPaymentAction();
        }

        /** @var ScopeConfigInterface $scopeConfig */
        $scopeConfig = $objectManager->get(ScopeConfigInterface::class);
        $enable3ds = $scopeConfig->getValue('payment/stripecreditcards/enable_3ds', ScopeInterface::SCOPE_STORE);

        if ($enable3ds) {
            return 'authorize';
        }

        return parent::getConfigPaymentAction();
    }
}