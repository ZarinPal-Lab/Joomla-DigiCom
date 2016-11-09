<?php
/*
	* Author : Mohammad Hossein Miri - Miri.Joomina@Gmail.Com
	* @JoominaMarket.Com
	* @Joomina.Ir.
*/

defined('_JEXEC') or die;

if(!class_exists('DigiComSiteHelperLog'))
	require JPATH_SITE."/components/com_digicom/helpers/log.php";

class  plgDigiCom_PayZarinpal extends JPlugin
{
	/**
	 * Load the language file on instantiation. Note this is only available in Joomla 3.1 and higher.
	 * If you want to support 3.0 series you must override the constructor
	 *
	 * @var    boolean
	 * @since  3.1
	 */
	protected $autoloadLanguage = true;

	/*
	* initialized response status for quickr use
	*/
	protected $responseStatus;

/*
	* Author : Mohammad Hossein Miri - Miri.Joomina@Gmail.Com
	* @JoominaMarket.Com
	* @Joomina.Ir.
*/

	function __construct($subject, $config)
	{
		parent::__construct($subject, $config);

		//Define Payment Status codes in API  And Respective Alias in Framework
		$this->responseStatus= array (
			'Completed' => 'A',
			'Pending' 	=> 'P',
			'Failed' 		=> 'P',
			'Denied' 		=> 'P',
			'Refunded'	=> 'RF'
		);
	}

/*
	* Author : Mohammad Hossein Miri - Miri.Joomina@Gmail.Com
	* @JoominaMarket.Com
	* @Joomina.Ir.
*/
	public function onDigicomSidebarMenuItem()
	{
		$pluginid = $this->getPluginId('zarinpal','digicom_pay','plugin');
		$params 	= $this->params;
		$link 		= JRoute::_("index.php?option=com_plugins&client_id=0&task=plugin.edit&extension_id=".$pluginid);

		return '<a target="_blank" href="' . $link . '" title="'.JText::_("PLG_DIGICOM_ZARINPAL").'" id="plugin-'.$pluginid.'">' . JText::_("PLG_DIGICOM_ZARINPAL_NICKNAME") . '</a>';

	}

	/*
	* method buildLayoutPath
	* @layout = ask for tmpl file name, default is default, but can be used others name
	* return propur file to take htmls
	*/
	function buildLayoutPath($layout)
	{
		
		if(empty($layout)) $layout = "default";

		// bootstrap2 check
		$bootstrap2 	= $this->params->get( 'bootstrap2' , 0);
		if($bootstrap2){
			$layout = "bootstrap2";
		}
		$app = JFactory::getApplication();

		// core path
		$core_file 	= dirname(__FILE__) . '/' . $this->_name . '/tmpl/' . $layout . '.php';

		// override path from site active template
		$override	= JPATH_BASE .'/templates/' . $app->getTemplate() . '/html/plugins/' . $this->_type . '/' . $this->_name . '/' . $layout . '.php';

		if(JFile::exists($override))
		{
			$file = $override;
		}
		else
		{
  		$file =  $core_file;
		}

		return $file;

	}

/*
	* Author : Mohammad Hossein Miri - Miri.Joomina@Gmail.Com
	* @JoominaMarket.Com
	* @Joomina.Ir.
*/
	function buildLayout($vars, $layout = 'default' )
	{
		
		// Load the layout & push variables
		ob_start();
		$layout = $this->buildLayoutPath($layout);
		
		// **********************
		// start bank
		
		$MerchantID = $vars->merchant;
		$Amount = (int)$vars->amount;
		$Amount = $Amount / 10;
		$order_id = $vars->order_id;
		$Description = 'توضیحات تراکنش'; // Required
		$Email = 'UserEmail@Mail.Com'; // Optional
		$Mobile = '09123456789'; // Optional
		$CallbackURL = $vars->return;
		
		$session =& JFactory::getSession();
		$session->set( 'price', $Amount);
		$session->set( 'order_id', $order_id);
		$client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8'));

		$result = $client->PaymentRequest(
			array(
				'MerchantID' => $MerchantID,
				'Amount' => $Amount,
				'Description' => $Description,
				'Email' => $Email,
				'Mobile' => $Mobile,
				'CallbackURL' => $CallbackURL
			)
		);

		//Redirect to URL You can do it also by creating a form
		if ($result->Status == 100) {
			
			if($vars->zaringate == "1")
				$url =  "https://www.zarinpal.com/pg/StartPay/".$result->Authority."/ZarinGate";
			elseif ($vars->zaringate == "0")
				$url =  "https://www.zarinpal.com/pg/StartPay/".$result->Authority;
				
			include($layout);
			$html = ob_get_contents();
			ob_end_clean();
			return $html;


		} else {
			echo'ERR: '.$result->Status;
		}
		
	}

	/*
	* method onDigicom_PayGetInfo
	* can be used Build List of Payment Gateway in the respective Components
	* for payment process its not used
	*/
	function onDigicom_PayGetInfo($config)
	{

		if(!in_array($this->_name,$config)) return;

		$obj 				= new stdClass;
		$obj->name 	=	$this->params->get( 'plugin_name' );
		$obj->id		= $this->_name;
		return $obj;
	}

/*
	* Author : Mohammad Hossein Miri - Miri.Joomina@Gmail.Com
	* @JoominaMarket.Com
	* @Joomina.Ir.
*/
	function onDigicom_PayGetHTML($vars, $pg_plugin)
	{
		if($pg_plugin != $this->_name) return;
		$params 					= $this->params;
		$zaringate 					= $params->get('zaringate');
		$merchant 					= $params->get('merchant');
		$vars->merchant 		= $merchant;
		$vars->zaringate 		= $zaringate;

		$html = $this->buildLayout($vars);
		return $html;

	}


/*
	* Author : Mohammad Hossein Miri - Miri.Joomina@Gmail.Com
	* @JoominaMarket.Com
	* @Joomina.Ir.
*/
	function onDigicom_PayProcesspayment($data)
	{

		$processor = JFactory::getApplication()->input->get('processor','');
		if($processor != $this->_name) return;

		//$verify 		= plgDigiCom_PayPaypalHelper::validateIPN($data);
		$session =& JFactory::getSession();
		$Amount = (int)$session->get('price',0);
		$order_id = $session->get('order_id',0);
		$Authority = $_GET['Authority'];
		
		$params 					= $this->params;
		$MerchantID					= $params->get('merchant');
		$zaringate 					= $params->get('zaringate');
		
		if ($_GET['Status'] == 'OK') {

			$client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8'));
			
			$result = $client->PaymentVerification(
				array(
					'MerchantID' => $MerchantID,
					'Authority' => $Authority,
					'Amount' => $Amount,
				)
			);
			
			if ($result->Status == 100) {
				
				echo "تراکنش موفقیت آمیز بود. کد رهگیری بانک : ".$result->RefID;
				$data['payment_status'] = "Completed";
				
				$info = array(
					'orderid' => $order_id,
					'data' => $data,
					'plugin' => ''
				);
			
				// set transaction log
				DigiComSiteHelperLog::setLog('transaction', 'cart proccessSuccess', $order_id, 'code rahgiri : '.$result->RefID, json_encode($info), 'success');
			} else {
				echo "تراکنش با شکست مواجه شده است " . $result->Status;
				$data['payment_status'] = "Failed";
			}
		} else {
			echo 'خرید توسط کاربر لغو شد';
			$data['payment_status'] = "Failed";
		}

		$payment_status = $this->translateResponse( $data );
		$Amount = $Amount * 10;
		
		$result = array(
			'order_id'				=> $order_id,
			'transaction_id'	=> $result->RefID,
			'status'					=> $payment_status,
			'total_paid_amt'	=> $data['payment_status'] == "Completed" ? $Amount : '',
			'error'						=>	'',
			'raw_data'				=> json_encode($data),
			'processor'				=> 'zarinpal'
		);

		return $result;
	}

/*
	* Author : Mohammad Hossein Miri - Miri.Joomina@Gmail.Com
	* @JoominaMarket.Com
	* @Joomina.Ir.
*/
	function translateResponse($data)
	{
		$payment_status = $data['payment_status'];

		if(array_key_exists($payment_status, $this->responseStatus))
		{
			return $this->responseStatus[$payment_status];
		}
	}

/*
	* Author : Mohammad Hossein Miri - Miri.Joomina@Gmail.Com
	* @JoominaMarket.Com
	* @Joomina.Ir.
*/
	function onDigicom_PayStorelog($name, $data)
	{
		if($name != $this->_name) return;
		//plgDigiCom_PayPaypalHelper::Storelog($this->_name,$data);
	}

/*
	* Author : Mohammad Hossein Miri - Miri.Joomina@Gmail.Com
	* @JoominaMarket.Com
	* @Joomina.Ir.
*/
	function getPluginId($element,$folder, $type)
	{
	    $db = JFactory::getDBO();
	    $query = $db->getQuery(true);
	    $query
	        ->select($db->quoteName('a.extension_id'))
	        ->from($db->quoteName('#__extensions', 'a'))
	        ->where($db->quoteName('a.element').' = '.$db->quote($element))
	        ->where($db->quoteName('a.folder').' = '.$db->quote($folder))
	        ->where($db->quoteName('a.type').' = '.$db->quote($type));

	    $db->setQuery($query);
	    $db->execute();
	    if($db->getNumRows()){
	        return $db->loadResult();
	    }
	    return false;
	}

}
