<?php
class ControllerExtensionTranslateDtl extends Controller{

	private $error = array();

	public function index(){
		$this->load->language('extension/translate/dtl');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('translate_dtl', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=translate', true));
		}

		if (isset($this->error['warning'])){
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['dtlapikey'])){
			$data['error_dtlapikey'] = $this->error['dtlapikey'];
		} else {
			$data['error_dtlapikey'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=translate', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/translate/dtl', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/translate/dtl', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=translate', true);

		$data['text_edit'] = $this->language->get('text_edit');

		if (isset($this->request->post['translate_dtl_apikey'])){
			$data['translate_dtl_apikey'] = $this->request->post['translate_dtl_apikey'];
		} else {
			$data['translate_dtl_apikey'] = (string)$this->config->get('translate_dtl_apikey');
		}

		if (isset($this->request->post['translate_dtl_status'])){
			$data['translate_dtl_status'] = $this->request->post['translate_dtl_status'];
		} else {
			$data['translate_dtl_status'] = $this->config->get('translate_dtl_status');
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/translate/dtl', $data));
	}


	protected function validate(){
		if (!$this->user->hasPermission('modify', 'extension/translate/dtl')){
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}


	public function install(){

	}


	public function uninstall(){

	}

	public function send(){
		$json = array();
		// $json['request'] = $this->request;
		
		if (isset($this->request->post['text']) && isset($this->request->post['source_lang'])){
			$dtlapikey = $this->config->get('translate_dtl_apikey');
			$source_lang = $this->request->post['source_lang'];
			$source_lang_code = explode('-', $source_lang)[0];

			$target_langs = [];
			if(isset($this->request->post['target_code'])){
				$target_langs[$this->request->post['target_code']] = explode('-', $this->request->post['target_code'])[0];
			} else {
				$this->load->model('localisation/language');
				$languages = $this->model_localisation_language->getLanguages();
				foreach($languages as $lang){
					if($lang['code'] == $source_lang) continue; // Exclude source lang
					$target_langs[$lang['code']] = explode('-', $lang['code'])[0];
				}
			}

			$text = $this->request->post['text'];
			$text_arr = json_decode(html_entity_decode($text, ENT_QUOTES, 'UTF-8'), true);
			
			$text = [];
			$text[] = $text_arr[$source_lang]['title'];
			$text[] = $text_arr[$source_lang]['description'];

			unset($text_arr[$source_lang]);

			foreach($target_langs as $lang_code => $target_lang){
				$curl = curl_init();
				$headers = [];
				$headers[] = 'Content-Type: application/json';
				$headers[] = 'Authorization: DeepL-Auth-Key ' . $dtlapikey;
				curl_setopt($curl, CURLOPT_URL, 'https://api-free.deepl.com/v2/translate');
				curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(
					array(
						'text' => $text,
						'source_lang' => $source_lang_code,
						'target_lang' => $target_lang
					)
				));
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($curl, CURLOPT_HEADER, 0);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
				curl_setopt($curl, CURLOPT_TIMEOUT, 30);

				$response = curl_exec($curl);

				if($response && curl_getinfo($curl, CURLINFO_HTTP_CODE) === 200){
					$json[$lang_code] = json_decode($response, true);
				}
				curl_close($curl);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
