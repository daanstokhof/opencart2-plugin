<?php

class Pay_Controller_Admin extends Controller {

    protected $_paymentOptionId;
    protected $_paymentMethodName;
    protected $_defaultLabel;

    protected $data = array();
    
    protected $error;
    
    public function index() {
        $this->load->language('extension/payment/' . $this->_paymentMethodName); // . $payment);
        $this->load->model('setting/setting');
        $this->document->setTitle('Pay.nl - ' . $this->_defaultLabel);

        $this->data['text_edit'] = 'Pay.nl - ' . $this->_defaultLabel; 
                
        $this->data['error_warning'] = '';
        $this->data['error_apitoken'] = '';
        $this->data['error_serviceid'] = '';

        
        $settings = $this->model_setting_setting->getSetting('paynl');


        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $newSettings = $this->request->post;
            $newSettings = array_merge($settings, $newSettings);
            $newSettings['paynl_apitoken'] = $newSettings[$this->_paymentMethodName . '_apitoken'];
            $newSettings['paynl_serviceid'] = $newSettings[$this->_paymentMethodName . '_serviceid'];


            $this->model_setting_setting->editSetting('paynl', $newSettings);
            $this->session->data['success'] = $this->language->get('text_success');

            //$this->response->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'));
        }


        if (!empty($this->error)) {
            if (!empty($this->error['warning'])) {
                $this->data['error_warning'] = $this->error['warning'];
            }
            if (!empty($this->error['apitoken'])) {
                $this->data['error_apitoken'] = $this->error['apitoken'];
            }

            if (!empty($this->error['serviceid'])) {
                $this->data['error_serviceid'] = $this->error['serviceid'];
            }
        }


        $this->data['payment_method_name'] = $this->_paymentMethodName;


        //gegevens uit de config laden
        $this->data['status'] = '0';
        if (isset($this->request->post[$this->_paymentMethodName . '_status'])) {
            $this->data['status'] = $this->request->post[$this->_paymentMethodName . '_status'];
        } elseif (isset($settings[$this->_paymentMethodName . '_status'])) {
            $this->data['status'] = $settings[$this->_paymentMethodName . '_status'];
        }

        //gegevens uit de config laden
        if (isset($this->request->post[$this->_paymentMethodName . '_label'])) {
            $this->data['label'] = $this->request->post[$this->_paymentMethodName . '_label'];
        } elseif (isset($settings[$this->_paymentMethodName . '_label'])) {
            $this->data['label'] = $settings[$this->_paymentMethodName . '_label'];
        }
        if (empty($this->data['label'])) {
            $this->data['label'] = $this->_defaultLabel;
        }

        $this->data['apitoken'] = '';
        if(!empty($this->request->post)) {
            if(isset($this->request->post[$this->_paymentMethodName . '_apitoken'])){
                $this->data['apitoken'] = $this->request->post[$this->_paymentMethodName . '_apitoken'];
            }
        }elseif (isset($settings[$this->_paymentMethodName . '_apitoken'])) {
            $this->data['apitoken'] = $settings[$this->_paymentMethodName . '_apitoken'];
        }elseif(isset($settings['paynl_apitoken'])) {
            $this->data['apitoken'] = $settings['paynl_apitoken'];          
        }
        
        $this->data['serviceid'] = '';
        if(!empty($this->request->post)) {
            if(isset($this->request->post[$this->_paymentMethodName . '_serviceid'])){
                $this->data['serviceid'] = $this->request->post[$this->_paymentMethodName . '_serviceid'];
            }
        }elseif (isset($settings[$this->_paymentMethodName . '_serviceid'])) {
            $this->data['serviceid'] = $settings[$this->_paymentMethodName . '_serviceid'];
        }elseif(isset($settings['paynl_serviceid'])) {
            $this->data['serviceid'] = $settings['paynl_serviceid'];          
        }
		
		if (isset($this->request->post[$this->_paymentMethodName . '_geo_zone_id'])) {
			$this->data['geo_zone_id'] = $this->request->post[$this->_paymentMethodName . '_geo_zone_id'];
		} else {
			$this->data['geo_zone_id'] = $this->config->get($this->_paymentMethodName . '_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');

		$this->data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();


        if (isset($this->request->post[$this->_paymentMethodName . '_confirm_on_start'])) {
            $this->data['confirm_on_start'] = $this->request->post[$this->_paymentMethodName . '_confirm_on_start'];
        } elseif (isset($settings[$this->_paymentMethodName . '_confirm_on_start'])) {
            $this->data['confirm_on_start'] = $settings[$this->_paymentMethodName . '_confirm_on_start'];
        }
        if (!isset($this->data['confirm_on_start'])) {
            $this->data['confirm_on_start'] = '1';
        }

        if (isset($this->request->post[$this->_paymentMethodName . '_send_status_updates'])) {
            $this->data['send_status_updates'] = $this->request->post[$this->_paymentMethodName . '_send_status_updates'];
        } elseif (isset($settings[$this->_paymentMethodName . '_send_status_updates'])) {
            $this->data['send_status_updates'] = $settings[$this->_paymentMethodName . '_send_status_updates'];
        }
        if (!isset($this->data['send_status_updates'])) {
            $this->data['send_status_updates'] = '1';
        }
        

        if (isset($this->request->post[$this->_paymentMethodName . '_completed_status'])) {
            $this->data['completed_status'] = $this->request->post[$this->_paymentMethodName . '_completed_status'];
        } elseif (isset($settings[$this->_paymentMethodName . '_completed_status'])) {
            $this->data['completed_status'] = $settings[$this->_paymentMethodName . '_completed_status'];
        }
        if (empty($this->data['completed_status'])) {
            $this->data['completed_status'] = 2;
        }

        if (isset($this->request->post[$this->_paymentMethodName . '_canceled_status'])) {
            $this->data['canceled_status'] = $this->request->post[$this->_paymentMethodName . '_canceled_status'];
        } elseif (isset($settings[$this->_paymentMethodName . '_canceled_status'])) {
            $this->data['canceled_status'] = $settings[$this->_paymentMethodName . '_canceled_status'];
        }

        if (empty($this->data['canceled_status'])) {
            $this->data['canceled_status'] = 7;
        }

        if (isset($this->request->post[$this->_paymentMethodName . '_pending_status'])) {
            $this->data['pending_status'] = $this->request->post[$this->_paymentMethodName . '_pending_status'];
        } elseif (isset($settings[$this->_paymentMethodName . '_pending_status'])) {
            $this->data['pending_status'] = $settings[$this->_paymentMethodName . '_pending_status'];
        }
        if (empty($this->data['pending_status'])) {
            $this->data['pending_status'] = 1;
        }

        $this->data['total'] = '';
        if (isset($this->request->post[$this->_paymentMethodName . '_total'])) {
            $this->data['total'] = $this->request->post[$this->_paymentMethodName . '_total'];
        } elseif (isset($settings[$this->_paymentMethodName . '_total'])) {
            $this->data['total'] = $settings[$this->_paymentMethodName . '_total'];
        }

        $this->data['totalmax'] = '';
        if (isset($this->request->post[$this->_paymentMethodName . '_totalmax'])) {
            $this->data['totalmax'] = $this->request->post[$this->_paymentMethodName . '_totalmax'];
        } elseif (isset($settings[$this->_paymentMethodName . '_totalmax'])) {
            $this->data['totalmax'] = $settings[$this->_paymentMethodName . '_totalmax'];
        }

        $this->data['sort_order'] = '';
        if (isset($this->request->post[$this->_paymentMethodName . '_sort_order'])) {
            $this->data['sort_order'] = $this->request->post[$this->_paymentMethodName . '_sort_order'];
        } elseif (isset($settings[$this->_paymentMethodName . '_sort_order'])) {
            $this->data['sort_order'] = $settings[$this->_paymentMethodName . '_sort_order'];
        }

        $this->data['instructions'] = '';
        if (isset($this->request->post[$this->_paymentMethodName . '_instructions'])) {
            $this->data['instructions'] = $this->request->post[$this->_paymentMethodName . '_instructions'];
        } elseif (isset($settings[$this->_paymentMethodName . '_instructions'])) {
            $this->data['instructions'] = $settings[$this->_paymentMethodName . '_instructions'];
        }

        $this->data['display_icon'] = '';
        if (isset($this->request->post[$this->_paymentMethodName . '_display_icon'])) {
            $this->data['display_icon'] = $this->request->post[$this->_paymentMethodName . '_display_icon'];
        } elseif (isset($settings[$this->_paymentMethodName . '_display_icon'])) {
            $this->data['display_icon'] = $settings[$this->_paymentMethodName . '_display_icon'];
        }



        $this->data['heading_title'] = $this->document->getTitle();

        $this->data['button_save'] = $this->language->get('button_save');
        $this->data['button_cancel'] = $this->language->get('button_cancel');

        $this->data['text_display_icon'] = $this->language->get('text_display_icon');
        $this->data['text_display_icon_tooltip'] = $this->language->get('text_display_icon_tooltip');

        $this->data['text_enabled'] = $this->language->get('text_enabled');
        $this->data['text_disabled'] = $this->language->get('text_disabled');
        $this->data['text_yes'] = $this->language->get('text_yes');
        $this->data['text_no'] = $this->language->get('text_no');

        $this->load->model('localisation/order_status');
        $this->data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $this->data['action'] = $this->url->link('extension/payment/' . $this->_paymentMethodName, 'token=' . $this->session->data['token'], 'SSL');
        $this->data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', 'SSL');

        $this->data['breadcrumbs'] = array();

        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => false
        );

        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_payment'),
            'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', 'SSL'),
            'separator' => ' :: '
        );

        $this->data['breadcrumbs'][] = array(
            'text' => $this->document->getTitle(),
            'href' => $this->url->link('extension/payment/' . $this->_paymentMethodName, 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        

        $this->data['header'] = $this->load->controller('common/header');
        $this->data['column_left'] = $this->load->controller('common/column_left');
        $this->data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('payment/paynl3.tpl', $this->data));
    }

    public function validate() {
        if (!$this->user->hasPermission('modify', "extension/payment/$this->_paymentMethodName")) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        // als de betaalmethode disabled is maken de instellingen niet uit
        if($this->request->post[$this->_paymentMethodName . '_status'] ==  0){
            return true;
        }
        
        if (!@$this->request->post[$this->_paymentMethodName . '_apitoken']) {
            $this->error['apitoken'] = 'U moet een apitokeninvoeren, u vind uw apitoken op: <a href="https://admin.pay.nl/my_merchant">https://admin.pay.nl/my_merchant</a> onderaan de pagina, onder API tokens';
        } else {

            try {
                $this->load->model('extension/payment/paynl3');
                
                        
                $serviceId = $this->request->post[$this->_paymentMethodName . '_serviceid'];
                $apiToken = $this->request->post[$this->_paymentMethodName . '_apitoken'];
                
                //eerst refreshen
                $this->model_extension_payment_paynl3->refreshPaymentOptions($serviceId, $apiToken);
                
                $paymentOption = $this->model_extension_payment_paynl3->getPaymentOption($serviceId, $this->_paymentOptionId);
                
                if(!$paymentOption){
                    $this->error['apitoken'] = "Deze betaalmethode is niet geactiveerd voor deze dienst. Ga naar  <a target='paynl' href='https://admin.pay.nl/programs/programs'>https://admin.pay.nl/programs/programs</a> om dit aan te passen.";
                }
            } 
            catch(Pay_Api_Exception $e){
                $this->error['apitoken'] = 'De Pay.nl Api gaf de volgende fout: '.$e->getMessage();
            }
            catch(Pay_Exception $e){
                $this->error['apitoken'] = 'Er is een fout opgetreden: '.$e->getMessage();
            }
            catch (Exception $e) {
                $this->error['apitoken'] = $e->getMessage();
            }
        }

        if (!@$this->request->post[$this->_paymentMethodName . '_serviceid']) {
            $this->error['serviceid'] = 'U moet een serviceId invoeren, u vind uw serviceId op: <a href="https://admin.pay.nl/programs/programs">https://admin.pay.nl/programs/programs</a>. Een serviceId begint altijd met SL-';
        }


      
        if (empty($this->error)){
            return true;
        } else {    
            return false;
        }
    }

    public function install() {
        $this->load->model('extension/payment/paynl3');

        $this->model_extension_payment_paynl3->createTables();
    }

}
