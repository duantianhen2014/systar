<?php
class SS_Controller extends CI_Controller{
	
	var $default_method='index';
	var $default_view_method;
	
	var $section_title='';
	
	var $require_login;
	var $gate_pages=array();//当前控制器下不需要登陆也能访问的页面方法名称

	var $company_type_model_loaded=false;
	var $company_model_loaded=false;
	
	var $controllers=array();
	
	function __construct(){
		parent::__construct();
		
		/*
		 * 处理$class和$method，并定义为常量
		 */
		global $class,$method;
		
		//使用controller中自定义的默认method
		if($method=='index'){
			$method=$this->default_method;
		}
		
		//定义$class常量，即控制器的名称
		define('CONTROLLER',$class);
		define('METHOD',$method);
		
		//CONTROLLER !=='gate' && $this->output->enable_profiler(TRUE);

		/*
		 * 自动载入的资源，没有使用autoload.php是因为后者载入以后不能起简称...
		 */
		$this->load->model('company_model','company');
		$this->load->model('people_model','people');
		$this->load->model('team_model','team');
		$this->load->model('user_model','user');
		$this->load->model('label_model','label');
		$this->load->model('message_model','message');
		
		if(is_file(APPPATH.'models/'.$this->company->type.'_model.php')){
			$this->load->model($this->company->type.'_model',$this->company->type);
			$this->company_type_model_loaded=true;
		}
		
		if(is_file(APPPATH.'models/'.$this->company->syscode.'_model.php')){
			$this->load->model($this->company->syscode.'_model',$this->company->syscode);
			$this->company_model_loaded=true;
		}
		
		if(is_file(APPPATH.'language/chinese/'.$this->company->syscode.'_lang.php')){
			$this->load->language($this->company->syscode);
		}
		
		$this->output->as_ajax=$this->input->is_ajax_request();
		
		if(CONTROLLER==='gate' && METHOD!=='browser' && $this->agent->browser()==='Internet Explorer' && $this->agent->version()<8){
			redirect('browser');
		}
		
		if(is_null($this->require_login)){
			if(in_array(METHOD,$this->gate_pages)){
				$this->require_login=false; 
			}else{
				$this->require_login=true;
			}
		}
	
		if($this->require_login && !$this->user->isLogged()){
			$this->output->status='login';
			$this->_output();
			exit;
		}

		$this->config->session=$this->session->all_userdata('config');
		
		$uri=$this->uri->uri_string;
		$get=$this->input->get();
		$post=$this->input->post();
		if($uri!=='polling' && ($get || $post)){
			$this->db->insert('log',array(
				'uri'=>$uri,
				'host'=>$this->input->server('HTTP_HOST'),
				'get'=>json_encode($get,256),
				'post'=>json_encode($post,256),
				'client'=>$this->input->user_agent(),
				//'duration'=>$this->benchmark->elapsed_time('total_execution_time_start'),
				'ip'=>$this->input->ip_address(),
				'company'=>$this->company->id,
				'username'=>$this->user->name,
				'time'=>date('Y-m-d H:i:s',$this->date->now)
			));
		}
	}
	
	/**
	 * @TODO 统一的_output方法最好还是写成Output:_display()的继承
	 * 
	 * 自定义的通用输出方法，系统不再直接将输出内容打印，而是传给此方法
	 * 此方法将当前Output类中的data,status,message等属性统一封装为json后输出
	 * 
	 * 而且他还会判断当前页面是否包含一个传统html输出
	 * 如果是，那么追加一些需要执行的内嵌js代码，然后添加到Output::data中（这里需要用内嵌js是因为js的变量需要由后台程序赋值）
	 */
	function _output($output=''){
		
		if(!$this->output->as_ajax){
			echo $output;
			return;
		}
		
		if($this->agent->browser()=='Internet Explorer' && strpos($this->input->header('Content-Type'),'multipart/form-data')!==false){
			header('Content-type: text/html');
		}
		else{
			header('Content-Type: application/json');
		}
		
		if($output){
			/*
			 * 如果在这个方法运行之前，页面就有输出，那么说明是一个旧式的输出html的页面
			 * 我们给它直接加上嵌入页面的js
			 * 并作为data的content键封装json传输到前段
			 */
			$output.=$this->load->view('innerjs',true);
			$this->output->setData($output,substr($this->input->server('REQUEST_URI'),1),'html','article>section[hash="'.substr($this->input->server('REQUEST_URI'),1).'"]');
		}
		
		if(array_key_exists('sidebar',$this->load->blocks)){
			$this->output->setData($this->load->blocks['sidebar'],'sidebar','html','aside>section[hash="'.substr($this->input->server('REQUEST_URI'),1).'"]');
		}
		
		if(is_null($this->output->status)){
			$this->output->status='success';
		}
		
		$output_array=array(
			'status'=>$this->output->status,
			'message'=>$this->output->message,
			'data'=>$this->output->data,
			'section_title'=>$this->section_title
		);
		
		echo json_encode($output_array);
	}
	
	/**
	 * 在一个form中，用户修改任何input/select值时，就发送一个请求，保存到$_SESSION中
	 * 如此一来到发生保存请求时，只需要把$_SESSION中的新值保存即可
	 */
	function setFields($item_id=NULL){

		$controller=CONTROLLER;
		$item_id && $this->$controller->id=$item_id;
		
		if(!is_array($this->input->post())){
			$this->output->status='fail';
			$this->output->message('invalid post data', 'warning');
			return;
		}
		
		foreach($this->input->post() as $field_name=>$value){
			post($field_name,$value);
		}
		$this->output->status='success';
	}
	
	function removeLabel($item_id){
		
		$controller=CONTROLLER;
		
		$label_name=$this->input->post('label');
		
		$this->$controller->removeLabel($item_id, $label_name);
	}
	
	function addLabel($item_id){
		$controller=CONTROLLER;
		
		$label_name=$this->input->post('label');
		
		$this->$controller->addLabel($item_id, $label_name);
	}
	
	function submit(){}
	
}
?>