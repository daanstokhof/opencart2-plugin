<?php

class Pay_Model extends Model {

    const STATUS_PENDING = 'PENDING';
    const STATUS_CANCELED = 'CANCELED';
    const STATUS_COMPLETE = 'COMPLETE';

    public function createTables() {
        $this->db->query("
                
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "paynl_transactions` (
                            `id` varchar(255) NOT NULL,
                            `orderId` int(11) NOT NULL,
                            `optionId` int(11) NOT NULL,
                            `optionSubId` int(11) DEFAULT NULL,
                            `amount` int(11) NOT NULL,
                            `status` varchar(255) NOT NULL,
                            `created` int(11) NOT NULL,
                            `last_update` int(11) DEFAULT NULL,
                            `start_data` text NOT NULL,
                            PRIMARY KEY (`id`)
                          ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ;
		");
        $this->db->query("                
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "paynl_paymentoptions` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `optionId` int(11) NOT NULL,
                            `serviceId` varchar(20) NOT NULL,
                            `name` varchar(255) NOT NULL,
                            `img` varchar(255) NOT NULL,
                            `update_date` datetime NOT NULL,
                            PRIMARY KEY (`id`)
                          ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ;
		");
        $this->db->query("                
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "paynl_paymentoption_subs` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `optionSubId` int(11) NOT NULL,   
                            `paymentOptionId` int(11) NOT NULL,                                                  
                            `name` varchar(255) NOT NULL,
                            `img` varchar(255) NOT NULL,
                            `update_date` datetime NOT NULL,
                            PRIMARY KEY (`id`)
                          ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ;
		");
    }

    public function addTransaction($transactionId, $orderId, $optionId, $amount, $startData, $optionSubId = null) {
        $sql = "INSERT INTO `" . DB_PREFIX . "paynl_transactions` (id, orderId, optionId, optionSubId, amount, status, created, start_data) VALUES ("
                . "'" . $this->db->escape($transactionId) . "'"
                . ",'" . $this->db->escape($orderId) . "'"
                . ",'" . $this->db->escape($optionId) . "'"
                . "," . (is_null($optionSubId) ? 'NULL' : "'" . $this->db->escape($optionSubId) . "'")
                . ",'" . $this->db->escape($amount) . "'"
                . ", '" . self::STATUS_PENDING . "'"
                . ", UNIX_TIMESTAMP() "
                . ",'" . $this->db->escape(json_encode($startData)) . "'"
                . ")";
        return $this->db->query($sql);
    }

    public function refreshPaymentOptions($serviceId, $apiToken) {
        $serviceId = $this->db->escape($serviceId);
        //eerst de oude verwijderen
        $sql = "DELETE options,optionsubs  FROM `" . DB_PREFIX . "paynl_paymentoptions` as options "
                . "LEFT JOIN `" . DB_PREFIX . "paynl_paymentoption_subs` as optionsubs ON optionsubs.paymentOptionId = options.id "
                . "WHERE options.serviceId = '$serviceId'";
        $this->db->query($sql);

        //nieuwe ophalen
        $api = new Pay_Api_Getservice();
        $api->setApiToken($apiToken);
        $api->setServiceId($serviceId);
        $result = $api->doRequest();

        $imgBasePath = $result['service']['basePath'];
        foreach ($result['paymentOptions'] as $paymentOption) {
            $img = $imgBasePath . $paymentOption['path'] . $paymentOption['img'];

            //variabelen filteren
            $optionId = $this->db->escape($paymentOption['id']);
            $name = $this->db->escape($paymentOption['visibleName']);
            $img = $this->db->escape($img);

            $sql = "INSERT INTO `" . DB_PREFIX . "paynl_paymentoptions` "
                    . "(optionId, serviceId, name, img, update_date) VALUES "
                    . "('$optionId', '$serviceId', '$name', '$img', NOW())";
            $this->db->query($sql);

            $internalOptionId = $this->db->getLastId();
            foreach ($paymentOption['paymentOptionSubList'] as $optionSub) {

                $optionSubId = $optionSub['id'];
                $name = $optionSub['visibleName'];
                $img = $imgBasePath . $optionSub['path'] . $optionSub['img'];

                //variabelen filteren
                $optionSubId = $this->db->escape($optionSubId);
                $name = $this->db->escape($name);
                $img = $this->db->escape($img);

                $sql = "INSERT INTO `" . DB_PREFIX . "paynl_paymentoption_subs` "
                        . "(optionSubId, paymentOptionId, name, img, update_date) VALUES "
                        . "('$optionSubId', $internalOptionId, '$name', '$img', NOW() )";
                $this->db->query($sql);
            }
        }
    }

    public function getPaymentOption($serviceId, $paymentOptionId) {
        $serviceId = $this->db->escape($serviceId);
        $paymentOptionId = $this->db->escape($paymentOptionId);

        $sql = "SELECT * FROM `" . DB_PREFIX . "paynl_paymentoptions` WHERE serviceId = '$serviceId' AND optionId = '$paymentOptionId' LIMIT 1;";
        $result = $this->db->query($sql);

        $paymentOption = $result->row;
        if (empty($paymentOption)) {
            return false;
        }

        //kijken of er subs zijn
        $sql = "SELECT * FROM `" . DB_PREFIX . "paynl_paymentoption_subs` WHERE paymentOptionId = '" . $paymentOption['id'] . "' ORDER BY name ASC; ";
        $result = $this->db->query($sql);
        $optionSubs = $result->rows;
        $arrOptionSubs = array();
        if (!empty($optionSubs)) {
            foreach ($optionSubs as $optionSub) {
                $arrOptionSubs[] = array(
                    'id' => $optionSub['optionSubId'],
                    'name' => $optionSub['name'],
                    'img' => $optionSub['img'],
                    'update_date' => $optionSub['update_date'],
                );
            }
        }
        $arrPaymentOption = array(
            'id' => $paymentOption['optionId'],
            'name' => $paymentOption['name'],
            'optionSubs' => $arrOptionSubs,
            'img' => $paymentOption['img'],
            'update_date' => $paymentOption['update_date'],
        );

        return $arrPaymentOption;
    }

    public function getTransaction($transactionId) {
        $sql = "SELECT * FROM `" . DB_PREFIX . "paynl_transactions` WHERE id = '" . $this->db->escape($transactionId) . "' LIMIT 1;";
        $result = $this->db->query($sql);

        return $result->row;
    }

    /**
     * Get The statusses of the order.
     * Because the order can have multiple transactions, 
     * We have to check here if the order hasn't already been completed
     */
    public function getStatussesOfOrder($orderId) {
        $sql = "SELECT `status` FROM `" . DB_PREFIX . "paynl_transactions` WHERE orderId = '" . $this->db->escape($orderId) . "';";
        $result = $this->db->query($sql);

        $rows = $result->rows;
        $result = array();
        foreach ($rows as $row) {
            $result[] = $row['status'];
        }
        return $result;
    }

    public function updateTransactionStatus($transactionId, $status) {
        if (!in_array($status, array(self::STATUS_CANCELED, self::STATUS_COMPLETE, self::STATUS_PENDING))) {
            throw new Pay_Exception('Invalid transaction status');
        }
        //safety so processed orders cannot go to canceled
        $transaction = $this->getTransaction($transactionId);

        if (empty($transaction)) {
            throw new Pay_Exception('Transaction not found');
        }

        //Because an order can have multiple transactions, we have to look for the status complete in all transactions for this order.
        $orderStatusses = self::getStatussesOfOrder($transaction['orderId']);

        if (in_array(self::STATUS_COMPLETE, $orderStatusses) && $status != self::STATUS_COMPLETE) {
            throw new Pay_Exception('Order already complete');
        }


        if ($transaction['status'] == $status) {
            //status is not changed
            return true;
        }

        $sql = "UPDATE `" . DB_PREFIX . "paynl_transactions` SET status = '$status' , last_update = UNIX_TIMESTAMP() WHERE id = '" . $this->db->escape($transactionId) . "'";

        return $this->db->query($sql);
    }

    public function getMethod($address = false, $total = false) {
        if (!$this->config->get($this->_paymentMethodName . '_status')) {
            return false;
        }

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int) $this->config->get($this->_paymentMethodName . '_geo_zone_id') . "' AND country_id = '" . (int) $address['country_id'] . "' AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')");

        if ($total) {
            if ($this->config->get($this->_paymentMethodName . '_total') && $total < $this->config->get($this->_paymentMethodName . '_total')) {
                return false;
            }
            if ($this->config->get($this->_paymentMethodName . '_totalmax') && $total > $this->config->get($this->_paymentMethodName . '_totalmax')) {
                return false;
            }
        }
        if ($this->config->get($this->_paymentMethodName . '_geo_zone_id')) {
            if ($query->num_rows == 0) {
                return false;
            }
        }

        $icon = "";
        if ($this->config->get($this->_paymentMethodName . '_display_icon') != '') {
            $iconSize = $this->config->get($this->_paymentMethodName . '_display_icon');
            $icon = "<img class='paynl_icon' src=\"https://www.pay.nl/images/payment_profiles/$iconSize/$this->_paymentOptionId.png\"> ";
        }
        $data = array(
            'code' => $this->_paymentMethodName,
            'title' => $icon . $this->getLabel(),
            'terms' => '',
            'sort_order' => $this->config->get($this->_paymentMethodName . '_sort_order')
        );
        return $data;
    }

    public function getLabel() {
        return $this->config->get($this->_paymentMethodName . '_label');
    }

    public function isAlreadyPaid($orderId) {
        //Because an order can have multiple transactions, we have to look for the status complete in all transactions for this order.
        $orderStatusses = $this->getStatussesOfOrder($orderId);

        if (in_array(self::STATUS_COMPLETE, $orderStatusses)) {
            return true;
        }
        return false;
    }

    public function processTransaction($transactionId) {
        $this->load->model('setting/setting');
        $this->load->model('checkout/order');


        $settings = $this->model_setting_setting->getSetting('paynl');


        $statusPending = $settings[$this->_paymentMethodName . '_pending_status'];
        $statusComplete = $settings[$this->_paymentMethodName . '_completed_status'];
        $statusCanceled = $settings[$this->_paymentMethodName . '_canceled_status'];

        $transaction = $this->getTransaction($transactionId);
        $apiInfo = new Pay_Api_Info();
        $apiInfo->setApiToken($settings[$this->_paymentMethodName . '_apitoken']);
        $apiInfo->setServiceId($settings[$this->_paymentMethodName . '_serviceid']);
        $apiInfo->setTransactionId($transactionId);

        $result = $apiInfo->doRequest();

        $state = $result['paymentDetails']['state'];
        $status = self::STATUS_PENDING;
        $orderStatusId = $statusPending;
        if ($state == 100 // PAID
        ||  $state == 95) // AUTHORIZED
        {
            $status = self::STATUS_COMPLETE;
            $orderStatusId = $statusComplete;
        } else if ($state < 0) {
            $status = self::STATUS_CANCELED;
            $orderStatusId = $statusCanceled;
        }
        if ($this->isAlreadyPaid($transaction['orderId'])) {
            // order is al betaald, 
            return self::STATUS_COMPLETE;
        }

        //status updaten
        $this->updateTransactionStatus($transactionId, $status);

        $message = "Pay.nl Updated order to $status.";
        //order updaten
        $order_info = $this->model_checkout_order->getOrder($transaction['orderId']);
        if(substr($order_info['payment_code'], 0,5) != 'paynl'){
            return "Error, last method is not a paynl method";
        }
        if ($order_info['order_status_id'] != $orderStatusId) {
            //alleen updaten als de status daadwerkelijk devveranderd, ivm exchange, de order wordt 2 keer aangepast
            if ($settings[$this->_paymentMethodName . '_send_status_updates'] == 1) {
                $send_status_update = true;
            } else {
                $send_status_update = false;
            }
            if($order_info['order_status_id'] == 0 && $status != self::STATUS_COMPLETE && !$send_status_update){
                // hij is nog niet confirmed, we gaan hem alleen opslaan als status complete is
                return $status;
            }
            $this->model_checkout_order->addOrderHistory($order_info['order_id'], $orderStatusId, $message, $send_status_update);
        }

        return $status;
    }

}
