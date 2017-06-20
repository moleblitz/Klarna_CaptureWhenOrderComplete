<?php
/**
 * Class Klarna_CaptureWhenOrderComplete_Model_Observer
 */
class Klarna_CaptureWhenOrderComplete_Model_Observer
{
    const LOG_LOCATION = 'klarna_capture.log';
    /**
     * @param Varien_Event_Observer $observer
     */
    public function captureInvoices(Varien_Event_Observer $observer)
    {
        if (Mage::getStoreConfig('payment/klarna_capturewhenordercomplete/active')) {
            /** @var Mage_Sales_Model_Order $order */
            $order = $observer->getEvent()->getShipment()->getOrder();
            if ($this->isKlarnaPayment($order)) {
                $invoices = $order->getInvoiceCollection();
                /** @var Mage_Sales_Model_Order_Invoice $invoice */
                foreach ($invoices as $invoice) {
                    if ($invoice->getState() == Mage_Sales_Model_Order_Invoice::STATE_OPEN) {
                        try {
                            $invoice->pay();
                            $invoice->capture()->save();
                            $order->addStatusHistoryComment(
                                "Invoice {$invoice->getIncrementId()} captured online."
                            )->save();
                        } catch (Mage_Core_Exception $e) {
                            Mage::logException($e);
                            $order->addStatusHistoryComment(
                                "Error whilst capturing invoice {$invoice->getIncrementId()}."
                            )->save();
                        }
                    }
                }
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function kcoCreateInvoice(Varien_Event_Observer $observer)
    {
        if (Mage::getStoreConfig('payment/klarna_capturewhenordercomplete/active')) {
            /** @var Mage_Sales_Model_Order $order */
            $order = $observer->getEvent()->getOrder();
            if ($this->isKlarnaPayment($order)) {
                if ($order->canInvoice()) {
                    try {
                        /** @var Mage_Sales_Model_Order_Invoice $invoice */
                        $invoice = $order->prepareInvoice();
                        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::NOT_CAPTURE);
                        $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN);
                        $invoice->register();
                        Mage::getModel('core/resource_transaction')
                            ->addObject($invoice)
                            ->addObject($order)
                            ->save();
                        $order->addStatusHistoryComment(
                            "Invoice {$invoice->getIncrementId()} created automatically"
                        )->save();
                    } catch (Mage_Core_Exception $e) {
                        Mage::logException($e);
                        $order->addStatusHistoryComment('Error whilst creating an invoice automatically')->save();
                    }

                    return $this;
                }

                Mage::log("Klarna order {$order->getIncrementId()} cannot create invoice", Zend_Log::ALERT, self::LOG_LOCATION);
                $order->addStatusHistoryComment('Cannot create invoice automatically')->save();
            }
        }
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function isKlarnaPayment($order)
    {
        return $order->getPayment()->getMethod() == 'klarna_kco';
    }

}
