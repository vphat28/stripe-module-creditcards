<?php

namespace Stripeofficial\CreditCards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Stripeofficial\Core\Model\Cron\Webhook;
use Stripeofficial\Core\Model\ResourceModel\Charge as ChargeResource;
use Stripeofficial\Core\Model\ChargeFactory;
use Stripeofficial\CreditCards\Gateway\Config\Config;

class DataAssignAfterCharge implements ObserverInterface
{
    /**
     * @var ChargeResource
     */
    protected $chargeResource;

    /**
     * @var ChargeFactory
     */
    protected $chargeFactory;

    public function __construct(
        ChargeResource $chargeResource,
        ChargeFactory $chargeFactory
    ) {
        $this->chargeResource = $chargeResource;
        $this->chargeFactory = $chargeFactory;
    }

    /**
     * @param Observer $observer
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getData('order');
        $chargeId = $observer->getData('charge_id');

        // Saving charge to database
        $charge = $this->chargeFactory->create();
        $charge->setData('charge_id', $chargeId);
        $charge->setData('reference_order_id', $order->getId());
        $this->chargeResource->save($charge);
    }
}
