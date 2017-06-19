<?php

class Klarna_CaptureWhenOrderComplete_Model_Observer
{
    public function salesOrderSaveCommitAfter(Varien_Event_Observer $observer)
    {
        if (Mage::getStoreConfig('payment/klarna_capturewhenordercomplete/active')) {
            $order = $observer->getEvent()->getOrder();
            if ($this->isKlarnaPayment($order)) {
                if($order->getState() === Mage_Sales_Model_Order::STATE_COMPLETE) {
                    $invoices = $order->getInvoiceCollection();
                    foreach ($invoices as $invoice) {
                        try {
                            $invoice->capture();
                            $order->addStatusHistoryComment('Invoice ' . $invoice->getIncrementId() . ' captured online.');
                        } catch (Mage_Core_Exception $e) {
                            Mage::logException($e);
                            $order->addStatusHistoryComment('Error whilst capturing invoice ' . $invoice->getIncrementId() . '.');
                        }
                    }
                }
            }
        }
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @throws Exception
     */
    public function kcoCreateInvoice(Varien_Event_Observer $observer)
    {
        if (Mage::getStoreConfig('payment/klarna_capturewhenordercomplete/active')) {
            $order = $observer->getEvent()->getOrder();
            if ($this->isKlarnaPayment($order)) {
                if ($order->canInvoice()) {
                    try {
                        $invoice = $order->prepareInvoice();
                        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::NOT_CAPTURE);
                        $invoice->register()->pay();
                        Mage::getModel('core/resource_transaction')
                            ->addObject($invoice)
                            ->addObject($invoice->getOrder())
                            ->save();
                        $order->addStatusHistoryComment('Invoice ' . $invoice->getIncrementId() . ' created automatically');
                    } catch (Mage_Core_Exception $e) {
                        Mage::logException($e);
                        $order->addStatusHistoryComment('Error whilst creating an invoice automatically');
                    }
                } else {
                    Mage::log('Klarna order ' . $order->getIncrementId() . ' cannot create invoice');
                    $order->addStatusHistoryComment('Cannot create invoice automatically');
                }
            $order->save();
            }
        }
    }

    public function isKlarnaPayment($order)
    {
        $payment = $order->getPayment();

        if ($payment->getMethod() != 'klarna_kco') {
            return false;
        }

        return true;
    }

}
