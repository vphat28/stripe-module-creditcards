<?php

namespace Stripeofficial\CreditCards\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Stripeofficial\Core\Api\PaymentInterface;
use Stripeofficial\Core\Model\ResourceModel\Source as SourceRS;
use Stripeofficial\Core\Model\SourceFactory;
use Stripeofficial\CreditCards\Gateway\Config\Config;
use Stripeofficial\CreditCards\Model\Adapter;

class DataAssignAfterSuccessObserver implements ObserverInterface
{
    /**
     * @var PaymentInterface
     */
    protected $creditCardPayment;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var SourceRS
     */
    protected $sourceRs;

    /**
     * @var SourceFactory
     */
    protected $sourceFactory;

    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    protected $historyRepository;

    /**
     * @var ManagerInterface
     */
    protected $eventManager;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var Adapter */
    private $adapter;

    public function __construct(
        PaymentInterface $creditCardPayment,
        OrderRepositoryInterface $orderRepository,
        SourceRS $sourceRs,
        SourceFactory $sourceFactory,
        OrderStatusHistoryRepositoryInterface $historyRepository,
        ManagerInterface $eventManager,
        ScopeConfigInterface $scopeConfig,
        Adapter $adapter
    ) {
        $this->creditCardPayment = $creditCardPayment;
        $this->orderRepository = $orderRepository;
        $this->sourceFactory = $sourceFactory;
        $this->sourceRs = $sourceRs;
        $this->historyRepository = $historyRepository;
        $this->eventManager = $eventManager;
        $this->scopeConfig = $scopeConfig;
        $this->adapter = $adapter;
    }

    /**
     * @param Observer $observer
     * @throws
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getOrder();
        $orders = $observer->getOrders();

        if (!empty($orders)) {
            // Multishipping checkout
            return;
        }
        $payment = $order->getPayment();

        if ($payment->getMethod() != Config::CODE) {
            return;
        }

        // If found stripe source id store it in database
        if ($order->getData('stripe_source_id')) {
            $source = $this->sourceFactory->create();
            $source->setData('source_id', $order->getData('stripe_source_id'));
            $source->setData('reference_order_id', $order->getId());
            $payment->setAmountAuthorized(0);
            $comments = $order->getAllStatusHistory();

            foreach ($comments as $comment) {
                /** @var Order\Status\History $comment */
                $this->historyRepository->delete($comment);
            }

            $this->sourceRs->save($source);
        }

        $action = $this->adapter->getConfigPaymentAction();

        if ($action === 'authorize') {
            $order->setState('new');
            $order->setStatus('pending');
        }

        $this->orderRepository->save($order);

        $metadata = [
            'Magento Order ID' => $order->getIncrementId(),
            'customer_name' => $order->getCustomerName(),
            'customer_email' => $order->getCustomerEmail(),
            'order_id' => $order->getId(),
        ];
        $chargeId = $order->getData('stripe_charge_id');

        if (!empty($chargeId)) {
            $this->eventManager->dispatch('stripe_charge_completed', ['order' => $order, 'charge_id' => $chargeId]);
            $this->creditCardPayment->updateChargeMetadata($chargeId, $metadata, $order);
        }
    }
}
