<?php
namespace frontend\controllers;

use Yii;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\authclient\OAuth2;
use yii\authclient\clients;
use yii\authclient\ClientInterface;
use yii\helpers\Url;
use common\models\User;
use frontend\models\Host;
use frontend\models\HostImage;
use frontend\models\Message;
use frontend\models\Messagechat;
use frontend\models\SignupForm;
use frontend\models\Allrounder;
use frontend\models\Booking;
use common\amazon\S3;
use common\amazon\S3Exception;
use frontend\models\DynamicActiveRecord;
use frontend\controllers\Paypalddp;
use frontend\models\ProfileModel;
use common\components\paypal;


//include('Paypalddp.php');

//include(__DIR__ . '/../../vendor/googleplus/Google_Client.php');
//include(__DIR__ . '/../../vendor/googleplus/contrib/Google_Oauth2Service.php');

/**
 * Site controller
 */
 
class HostController extends Controller
{
	
    public $layout = 'main_home';
	public $userid = '';
	public $enableCsrfValidation = false;
			
    /**
     * @inheritdoc
     */
	 
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout','signup','fb_gm_signup','googleinfo','profile','login','list_space_first','locationmap','deleteimage','bookingstart','booked','demo','invoice','getpdfinvoice'],
                'rules' => [
                    [
                        'actions' => ['signup','profile','login','list_space_first','listing','locationmap','deleteimage'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout','profile','login','list_space_first','bookingstart','bookingrequest','locationmap','booked','demo','deleteimage','invoice','getpdfinvoice'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
		'auth' => [
                'class' => 'yii\authclient\AuthAction',
                'successCallback' => [$this, 'successCallback']
				
            ],
		
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

  	public function successCallback($client)
    {
        $attributes = $client->getUserAttributes();
        //user login or signup comes here
    }
	
    public function actionTest()
    {
	 $noOfMessage = 0;
	 if(isset($_SESSION['id']) && !empty($_SESSION['id']))
	 {
	  $user_id_s = $_SESSION['id'];
	  $messagecount  =  Message::find()->where("(host_id= :user_id or guest_id= :user_id) and read_status= :read_status",['user_id' => $user_id_s,'read_status'=>'0'])->count();	  
	  
	  $messagecount_chat  =  Messagechat::find()->where("receiver_id = :receiver_id and read_status= :read_status",['receiver_id' => $user_id_s,'read_status'=>'0'])->count();
	   $noOfMessage = $messagecount + $messagecount_chat;
	  }
	  
    }

	public function actionIndex()
	{
	
	    $gClient = new \Google_Client();
		$gClient->setApplicationName('hostguesthome');
		$gClient->setClientId(\Yii::$app->params['google_client_id']);
		$gClient->setClientSecret(\Yii::$app->params['google_client_secret']);
		$gClient->setRedirectUri(\Yii::$app->params['google_redirect_url']);
		$gClient->setDeveloperKey(\Yii::$app->params['google_developer_key']);
		//$gClient->setAccessType('online');
		//$gClient->setApprovalPrompt('auto');
		
		$google_oauthV2 = new \Google_Oauth2Service($gClient);

		//If user wish to log out, we just unset Session variable
		if (isset($_REQUEST['reset'])) 
		{
		   unset($_SESSION['token']);
		$gClient->revokeToken();
			header('Location: ' . filter_var(\Yii::$app->params['google_redirect_url'], FILTER_SANITIZE_URL)); 
			//redirect user back to page
		}
		
		//If code is empty, redirect user to google authentication page for code.
		//Code is required to aquire Access Token from google
		//Once we have access token, assign token to session variable
		//and we can redirect user back to page and login.
	
		//print_r($_REQUEST);
	
		if (isset($_REQUEST['code']) && !(isset($_SESSION['token']))) 
		{ 
		
		 $code = $_REQUEST['code'];
		//exit;
		$gClient->authenticate($code);
		
		
		$_SESSION['token'] = $gClient->getAccessToken();
		/* print_r($_SESSION['token']);
		echo 'testing google plus working, generate token...';
		exit; */
		//header('Location: ' . filter_var(\Yii::$app->params['google_redirect_url'], FILTER_SANITIZE_URL));
		/*echo ' <script>window.location.reload();</script>';*/
		//return ;
		}
		
		if (isset($_SESSION['token'])) 
		{ 
		$gClient->setAccessToken($_SESSION['token']);
		}
		
		$authUrl = '';
		if ($gClient->getAccessToken()) 
		{
		//For logged in user, get details from google using access token
		$user 				= $google_oauthV2->userinfo->get();
		$user_id 			= $user['id'];
		$user_name 			= filter_var($user['name'], FILTER_SANITIZE_SPECIAL_CHARS);
		$email 				= filter_var($user['email'], FILTER_SANITIZE_EMAIL);
		$profile_url 		= filter_var($user['link'], FILTER_VALIDATE_URL);
		$profile_image_url 	= filter_var($user['picture'], FILTER_VALIDATE_URL);
		$personMarkup 		= "$email<div><img src='$profile_image_url?sz=50'></div>";
		$_SESSION['token'] 	= $gClient->getAccessToken();		
		
		/*echo"<pre>";
		print_r($user);
		exit;*/
		
		}else{
		
		  
		  $authUrl = $gClient->createAuthUrl();
		  $this->view->params['authUrl'] = $authUrl;
		}
		
		  if(isset($_REQUEST['code'])){
		 // ob_start();
		  //echo"here now ";print_r($_R	EQUEST);die;
		  $model = new SignupForm();			
		  $get_response = $model->store_gmail_user($user);
		  //print_r($get_resource); exit;
		  if($get_response==1){
			
		  /*echo ' <script>window.location="http://localhost/hostandguest/site/index";</script>';*/
		  echo '<script>window.location="http://www.hostguesthome.com/site/index";</script>';
		  }
		  
		  }
		  
		  // $id= $_SESSION['id'];
		
		  
			//return $this->render('index',['authUrl'=> $authUrl]);

	  if(empty($_SESSION['id']))
	  {
		  $session = '';
		  return $this->render('host_index',['userid'=>$session,'authUrl'=> $authUrl]);
	  }
	  else 
	  {
		    $session = $_SESSION['id'];
			return $this->render('host_index',['userid'=>$session,'authUrl'=> $authUrl]);
	  }
		
	}
	
	public function actionLogin()
	{
		 
	}
	
	public function actionList_space_first()
	{
		
		$host_model = new Host;
		
			if (empty($_SESSION['id']))
			{
				return $this->redirect('index');	
 			}
			else
			{
			
					$main = $host_model->becomeHost();	
					//echo '<pre>';
					//print_r($main);
				
					if($main == 'no')
					{
						$error_code = 'Please enter valid address';		
						return $this->redirect('index?msg='.$error_code.'');
					}
					else
					{
						//die('found');
						return $this->redirect('listing/'.$main.'');
					}
			}
			
		exit;
   	}
	
	
	public function actionListing()
	{	
		// select all USERDATA according to last inserted hostid is $id
		
		$id = Yii::$app->request->get('id');
		if (!empty($id))
		{
			  $selectID = "select * from host where hostid = '".$id."'";
			  $userdata = Yii::$app->db->createCommand($selectID)->queryAll();
	  		  return $this->render('listing',['lastInsertid'=>$userdata]); 		
		}
				 
 		
			 // $id1 = Yii::$app->request->get('id');
			 if (!empty($_POST['hidden_id'])){
			 	 
				/*$bedroom = Yii::$app->request->post('bedroom');
				
				$beds = Yii::$app->request->post('beds');
				
				$bathrooms = Yii::$app->request->post('bathrooms');*/

				$hometype = Yii::$app->request->post('hometype');

				$roomtype = Yii::$app->request->post('roomtype');

				$accommodates = Yii::$app->request->post('accommodates');
				
				$cancelation = Yii::$app->request->post('flexible');
	
	
//	$update_roomandbeds = Yii::$app->db->createCommand()->update('host',['bedrooms' => $bedroom ,'beds' => $beds,'bathrooms' => $bathrooms ],'hostid ='.$id)->execute();
			
				/* $update = "update host set bedrooms = '".$bedroom."',
										beds = '".$beds."',
										bathrooms = '".$bathrooms."',
										hometype = '".$hometype."',
										roomtype = '".$roomtype."',
										accommodates = '".$accommodates."'
										where hostid = '".$_POST['hidden_id']."' and user_id = '".$_SESSION['id']."'";*/
										
										
										
				$update = "update host set hometype = '".$hometype."',
										roomtype = '".$roomtype."',
										accommodates = '".$accommodates."',
										cancellationpolicies = '".$cancelation."'
										where hostid = '".$_POST['hidden_id']."' and user_id = '".$_SESSION['id']."'";
																				
				$updateQuery = Yii::$app->db->createCommand($update)->execute();						
				//exit;
				return $this->redirect('calender/'.$_POST['hidden_id'].'');
			 }
	}
	
	
		public function actionCalender()
		{
			
			
				$id = Yii::$app->request->get('id');
				if (!empty($id))
				{
					
					  $selectID = "select * from host where hostid = '".$id."'";
					  $userdata = Yii::$app->db->createCommand($selectID)->queryAll();
					 
					  return $this->render('calender',['lastInsertid'=>$userdata]); 		
				}
			

		if (!empty($_POST)){
			
			//echo'<pre>'; print_r($_POST); die;
 							
						//if(!empty($_REQUEST['availablityType']) && $_REQUEST['availablityType'] == 1 ) 
						
						if(!empty($_POST['alw']))
						{
							//echo'<pre>'; print_r($_POST); die;
							
							$avl = 	Yii::$app->request->post('alw');
							$from = '';
							$to = '';
								
						}
						else {
							//echo'<pre>'; print_r($_POST); die;
							$avl ='';	
							$from = Yii::$app->request->post('checkin');
							$to = Yii::$app->request->post('checkout');
								
						}
							
							/*if (!empty($_POST['alw']))
							{
								$avl = 	Yii::$app->request->post('alw');
								$from = '';
								$to = '';
							}
							else 
							{
								$avl ='';	
								$from = Yii::$app->request->post('checkin');
								$to = Yii::$app->request->post('checkout');
							}*/

//UpdaTE Listing Availablity 							 
 $update = "UPDATE host SET from_date = '".$from."', to_date = '".$to."', available = '".$avl."' WHERE hostid = ".$_POST['hidden_id']." AND user_id = ".$_SESSION['id']." "; 

		
				$updateQuery = Yii::$app->db->createCommand($update)->execute();				 
				// exit;
				  return $this->redirect('pricing/'.$_POST['hidden_id'].'');	
				  					
				}
				//exit;
				//return $this->redirect('calender/'.$_POST['hidden_id'].'');
			 		
		}	
		
		
	public function actionPricing()
	{
	
		$id = Yii::$app->request->get('id');
		if (!empty($id)) {
			
			$defaultCurrency = (\Yii::$app->params['currencyCode']);
			
			/*$topCurrencies = array();
			$topCurrencies[$defaultCurrency] = $defaultCurrency;
			$topCurrencies['USD'] = 'USD';
			$topCurrencies['GBP'] = 'GBP';
			$topCurrencies['EUR'] = 'EUR';
			$topCurrencies['AUD'] = 'AUD';
			$topCurrencies['CAD'] = 'CAD';
	
			$finaltop = $topCurrencies;
			$finalTopCurrencies = array_unique($topCurrencies);
*/
			/* $strsql = "SELECT DISTINCT currencyCode FROM hgh_currencymanager WHERE currencyName !='' AND currencyCode NOT IN('USD','GBP','EUR','AUD','CAD','".$defaultCurrency."') "; */
			 
			  $strsql = "SELECT DISTINCT currencyCode FROM hgh_currencymanager WHERE currencyName !='' AND currencyCode IN('USD','GBP','EUR','AUD','CAD','INR') "; 
			 
			$currencyList = Yii::$app->db->createCommand($strsql)->queryAll();

			foreach($currencyList as $key=>$val) {
				$finalTopCurrencies[$val['currencyCode']] = $val['currencyCode'];
			}

			
			  $selectID = "select * from host where hostid = '".$id."'";
			  $userdata = Yii::$app->db->createCommand($selectID)->queryAll();
	  		  return $this->render('pricing',['lastInsertid'=>$userdata,'currencydata'=>$finalTopCurrencies]); 		
		}	
		
			if (isset($_POST['amount']) ){
					
					//print_r($_POST); exit;
					
						$main_currency = $_POST['currency'];
						$amount = Yii::$app->request->post('amount'); 
						$extra = Yii::$app->request->post('extracharge');
						$clening = Yii::$app->request->post('cleningchagre');
						$person = Yii::$app->request->post('person');
					 	$extraPerson = Yii::$app->request->post('extraperson');
						
						if (!empty($extraPerson)){$extraPerson = $extraPerson;} else {$extraPerson = 0;}

					 	$Breakfast = Yii::$app->request->post('breakfast');
						if (!empty($breakfast)){$breakfast = $breakfast;} else {$breakfast = 0;}

					 	$Lunch = Yii::$app->request->post('lunch');
						if (!empty($Lunch)){$Lunch = $Lunch;} else {$Lunch = 0;}

					 	$Dinner = Yii::$app->request->post('dinner');
						if (!empty($Dinner)){$Dinner = $Dinner;} else {$Dinner = 0;}

					 	$Tea = Yii::$app->request->post('tea');
						if (!empty($Tea)){$Tea = $Tea;} else {$Tea = 0;}

					 	$Coffee = Yii::$app->request->post('coffee');
						if (!empty($Coffee)){$Coffee = $Coffee;} else {$Coffee = 0;}

						$Airport  = Yii::$app->request->post('airport');
						if (!empty($Airport)){$Airport = $Airport;} else {$Airport = 0;}
						
						$GovernmenServiceTax  = Yii::$app->request->post('government_service_tax');
						if (!empty($GovernmenServiceTax)){ $government_service_tax = $GovernmenServiceTax; } else { $government_service_tax = 0; }
					 
					
							 
 				 $update = "update host set amount_pernight = '".$amount."',
				 						government_service_tax = '".$government_service_tax."',
										currency = '".$main_currency."',
										servicefee = '".$extra."',
										cleaningfee = '".$clening."',
										person = '".$person."',
										extraperson = '".$extraPerson."',
										breakfast = '".$Breakfast."',
										Lunch = '".$Lunch."',
										Dinner = '".$Dinner."',
										Tea = '".$Tea."',
										Coffee = '".$Coffee."',
										Airport = '".$Airport."'
										where hostid = '".$_POST['hidden_id']."' and user_id = '".$_SESSION['id']."'";
										
 				  $updateQuery = Yii::$app->db->createCommand($update)->execute();
				  return $this->redirect('overview/'.$_POST['hidden_id'].'');						
				
				}
				
	}	
	
	public function actionOverview()
	{
		$id = Yii::$app->request->get('id');
		
			if (!empty($id))
			{
				  $selectID = "select * from host where hostid = '".$id."'";
				  $userdata = Yii::$app->db->createCommand($selectID)->queryAll();
				  return $this->render('overview',['lastInsertid'=>$userdata]); 		
			}	
		
			if (isset($_POST['title']) )
			{
				 $title     = Yii::$app->request->post('title'); 
				 $summery   = Yii::$app->request->post('summery'); 
				 $mainTitle =  preg_replace('[^ A-Za-z0-9-]', '', $title); 
				 
				 
				 $update = "update host set title = '".mysql_real_escape_string($mainTitle)."',
										summery = '".mysql_real_escape_string($summery)."'
 										where hostid = '".$_POST['hidden_id']."' and user_id = '".$_SESSION['id']."'";
				 $updateQuery = Yii::$app->db->createCommand($update)->execute();
				 
				 return $this->redirect('photo/'.$_POST['hidden_id'].'');						
			}				
		
	}
	
	public function actionPhoto()
	{
		
		$id = Yii::$app->request->get('id');
		
		
		if (!empty($id))
		{
			  $selectID = "select * from host where hostid = '".$id."'";
			  $userdata = Yii::$app->db->createCommand($selectID)->queryAll();
	  		  return $this->render('photo',['lastInsertid'=>$userdata]); 		
		}
		
		
		//die('here');
		
		if (!empty($_REQUEST['file_count']))
		{ 
		
 					foreach($_FILES['my_file']['tmp_name'] as $key=>$tmp_name){	
					 $count = count($_FILES['my_file']['name'][$key]);
					  $size = filesize($_FILES['my_file']['tmp_name'][$key]); 
					 
					$img= $_FILES['my_file']['name'][$key];
					$unique= mt_rand(10,100);
					
					//$ext = pathinfo($_FILES['my_file']['name'][$i], PATHINFO_EXTENSION);
					$file_name = "user_".$unique.$img;
					//$file_thumb = "thumb_".$unique.$img;
					$new_name = "uploadedfile/hostImages/".$file_name."";
					
		// UPLOAD PHOTO TO S3
		$s3 = new S3(\Yii::$app->params['awsAccessKey'], \Yii::$app->params['awsSecretKey']);
		//retreive post variables
	 	$fileName = $file_name;
		$fileTempName = $_FILES["my_file"]["tmp_name"][$key];
		// main profile picture
		$s3->putObjectFile($fileTempName, "nanoweb", 'hostguesthome/uploadedfile/hostImages/'.$fileName, S3::ACL_PUBLIC_READ_WRITE);
					
			
					move_uploaded_file($_FILES["my_file"]["tmp_name"][$key], $new_name);
		 
				
					$insert = "insert into host_image set hostId = '".$_POST['hidden_id']."',
													  images = '".$file_name."'";
					$userdata = Yii::$app->db->createCommand($insert)->execute();										  
					if ($userdata)
						{
							echo '1';
						}
					
					}
			 
					 
					 
 					return $this->redirect('amenities/'.$_POST['hidden_id'].'');
		}
		else {
			return $this->redirect('amenities/'.$_REQUEST['hidden_id'].'');
		}
	  }
	  
	public function actionAmenities()
	{
		$id = Yii::$app->request->get('id');
		if (!empty($id))
		{
			  $selectID = "select * from host where hostid = '".$id."'";
			  $userdata = Yii::$app->db->createCommand($selectID)->queryAll();
	  		  return $this->render('amenities',['lastInsertid'=>$userdata]); 		
		}
		//echo'<pre>'; print_r($_POST); die;

			if (!empty($_POST)){

  				if (!empty($_POST['New_Soap'])){$New_Soap=$_POST['New_Soap'];}else{$New_Soap='';}
				if (!empty($_POST['Dry_cleaned'])){$dry = $_POST['Dry_cleaned'];}else {$dry='';}
				if (!empty($_POST['Kitchen'])){$Kitchen = $_POST['Kitchen'];}else {$Kitchen='';}
				if (!empty($_POST['Tea'])){$Tea = $_POST['Tea'];}else {$Tea='';}
				if (!empty($_POST['TV'])){$TV = $_POST['TV'];}else {$TV='';}
				if (!empty($_POST['Cable'])){$Cable = $_POST['Cable'];}else {$Cable='';}
				if (!empty($_POST['Heating'])){$Heating = $_POST['Heating'];}else {$Heating='';}
				if (!empty($_POST['Air'])){$Air = $_POST['Air'];}else {$Air='';}
				if (!empty($_POST['indoor'])){$indoor = $_POST['indoor'];}else {$indoor='';}
				if (!empty($_POST['Internet'])){$Internet = $_POST['Internet'];}else {$Internet='';}
				if (!empty($_POST['Parking'])){$Parking = $_POST['Parking'];}else {$Parking='';}
				if (!empty($_POST['Welcome'])){$Welcome = $_POST['Welcome'];}else {$Welcome='';}
				if (!empty($_POST['cabinshower'])){$cabinshower = $_POST['cabinshower'];}else {$cabinshower='';}
				if (!empty($_POST['jacuzzi'])){$jacuzzi = $_POST['jacuzzi'];}else {$jacuzzi='';}
					
					  $coman = $New_Soap.'-:-'.$dry.'-:-'.$Kitchen.'-:-'.$Tea.'-:-'.$TV.'-:-'.$Cable.'-:-'.$Heating.'-:-'.$Air.'-:-'.$indoor.'-:-'.$Internet.'-:-'.$Parking.'-:-'.$Welcome.'-:-'.$cabinshower.'-:-'.$jacuzzi;  
					 
					  
					 
	if (!empty($_POST['Extra_Mattress'])){$Extra_Mattress = $_POST['Extra_Mattress'];}else {$Extra_Mattress='';}
	if (!empty($_POST['Extra_Blanket'])){$Extra_Blanket = $_POST['Extra_Blanket'];}else {$Extra_Blanket='';}
	if (!empty($_POST['Washer'])){$Washer = $_POST['Washer'];}else {$Washer='';}
	if (!empty($_POST['Alarm_clock'])){$Alarm_clock = $_POST['Alarm_clock'];}else {$Alarm_clock='';}
	if (!empty($_POST['Laundry_Service'])){$Laundry_Service = $_POST['Laundry_Service'];}else {$Laundry_Service='';}
	if (!empty($_POST['Gym'])){$Gym = $_POST['Gym'];}else {$Gym='';}
	if (!empty($_POST['Swimming_Pool'])){$Swimming_Pool = $_POST['Swimming_Pool'];}else {$Swimming_Pool='';}
	if (!empty($_POST['Indoor_Fireplace'])){$Indoor_Fireplace = $_POST['Indoor_Fireplace'];}else {$Indoor_Fireplace='';}
	if (!empty($_POST['Intercom'])){$Intercom = $_POST['Intercom'];}else {$Intercom='';}
	if (!empty($_POST['Computer'])){$Computer = $_POST['Computer'];}else {$Computer='';}
	if (!empty($_POST['Doorman'])){$Doorman = $_POST['Doorman'];}else {$Doorman='';}
	if (!empty($_POST['Elevator'])){$Elevator = $_POST['Elevator'];}else {$Elevator='';}
	if (!empty($_POST['Made'])){$Made = $_POST['Made'];}else {$Made='';}
	if (!empty($_POST['tub'])){$tub = $_POST['tub'];}else {$tub='';}
					 
					 
		$extra = $Extra_Mattress.'-:-'.$Extra_Blanket.'-:-'.$Washer.'-:-'.$Alarm_clock.'-:-'.$Laundry_Service.'-:-'.$Gym.'-:-'.$Swimming_Pool.'-:-'.$Indoor_Fireplace.'-:-'.$Intercom.'-:-'.$Computer.'-:-'.$Doorman.'-:-'.$Elevator.'-:-'.$Made.'-:-'.$tub; 


	if (!empty($_POST['Smoking_Allowed'])){$Smoking_Allowed = $_POST['Smoking_Allowed'];}else {$Smoking_Allowed='';}
	if (!empty($_POST['Family_Children'])){$Family_Children = $_POST['Family_Children'];}else {$Family_Children='';}
	if (!empty($_POST['Pets_allowed'])){$Pets_allowed = $_POST['Pets_allowed'];}else {$Pets_allowed='';}
	if (!empty($_POST['Wheelchair'])){$Wheelchair = $_POST['Wheelchair'];}else {$Wheelchair='';}

	$features = $Smoking_Allowed.'-:-'.$Family_Children.'-:-'.$Pets_allowed.'-:-'.$Wheelchair; 


	if (!empty($_POST['Fire'])){$Fire = $_POST['Fire'];}else {$Fire='';}
	if (!empty($_POST['Smoke'])){$Smoke = $_POST['Smoke'];}else {$Smoke='';}
	if (!empty($_POST['Carbon'])){$Carbon = $_POST['Carbon'];}else {$Carbon='';}
	if (!empty($_POST['First_Aid'])){$First_Aid = $_POST['First_Aid'];}else {$First_Aid='';}


		$safety = $Fire.'-:-'.$Smoke.'-:-'.$Carbon.'-:-'.$First_Aid; 
					  
				$title = Yii::$app->request->post('title'); 
				$summery = Yii::$app->request->post('summery'); 
							 
 				 $update = "update host set mostcomman_facilities = '".$coman."',
										extra_facilities = '".$extra."',
										features_facilities = '".$features."',
										 	safety_facilities = '".$safety."'
 										where hostid = '".$_POST['hidden_id']."' and user_id = '".$_SESSION['id']."'";
				 
				 $updateQuery = Yii::$app->db->createCommand($update)->execute();
				 
				  return $this->redirect('listspace/'.$_POST['hidden_id'].'');						
				}				
		
 	}
 	
	public function actionListspace()
	{
		$id = Yii::$app->request->get('id');
		if (!empty($id))
		{
			  $selectID = "select * from host where hostid = '".$id."'";
			  $userdata = Yii::$app->db->createCommand($selectID)->queryAll();
	  		  return $this->render('listspace',['lastInsertid'=>$userdata]); 		
		}
			 if (!empty($_POST['Bedrooms'])) {
			  //echo'<pre>'; print_r($_POST); die;
			 
			// $arrname = array();
			// $arrdistance = array();
			 
			 if (!empty($_POST['city_c'])){$city = $_POST['city_c'];}else{$city='';}
			 if (!empty($_POST['city-center'])){$cityval = $_POST['city-center'];} else {$cityval = '';}
			 
			 if (!empty($_POST['bus_s'])){$bus = $_POST['bus_s'];}else{$bus='';}
			 if (!empty($_POST['bus-stop'])){$busval = $_POST['bus-stop'];} else {$busval = '';}

			 if (!empty($_POST['metro_u'])){$metro = $_POST['metro_u'];}else{$metro = '';}
			 if (!empty($_POST['metro-under'])){$metroval = $_POST['metro-under'];} else {$metroval = '';}

			 if (!empty($_POST['air'])){$air = $_POST['air'];}else{$air='';}
			 if (!empty($_POST['air-port'])){$airval = $_POST['air-port'];} else {$airval = '';}

			 if (!empty($_POST['train'])){$train = $_POST['train'];}else{$train='';}
			 if (!empty($_POST['train-station'])){$trainval = $_POST['train-station'];} else {$trainval = '';}

			 if (!empty($_POST['shop'])){$shop = $_POST['shop'];}else{$shop='';}
			 if (!empty($_POST['shopping-center'])){$shoppingval = $_POST['shopping-center'];} else {$shoppingval = '';}
			 
			 
				$finalname = $city.':'.$bus.':'.$metro.':'.$air.':'.$train.':'.$shop;
			
		 
			 
			  $finaldistace = $cityval.':'.$busval.':'.$metroval.':'.$airval.':'.$trainval.':'.$shoppingval;
			 
			 
			 
			 $count = $_POST['count']  ;

			/*$name =  Yii::$app->request->post('location_name');

			$distance =  Yii::$app->request->post('location_distance');

			if ($count != '')
			{	
				$arrname[] = $name;
				$arrdistance[] = $distance;
				for ($i=1;$i<=$count;$i++)
					{
						
						$arrname[] = $_POST['location_name'.$i];
						$arrdistance[] = $_POST['location_distance'.$i];
					}
				$finalname = implode(':',$arrname);
				$finaldistace = implode(':',$arrdistance);
			}
			else 
			{
				if(!empty($name)||!empty($distance))
				{
					
				$finalname = $name ;
				$finaldistace = $distance;
				}
				else
				{
					$finalname='';
					
					$finaldistace='';
				}
				
				
			}*/
			
				$bedroom = Yii::$app->request->post('Bedrooms');
				
				$beds = Yii::$app->request->post('Beds');
				
				$bathrooms = Yii::$app->request->post('Bathroom');
 				
				$bedtype = Yii::$app->request->post('Bed_Type');
				
 
			//	$update_roomandbeds = Yii::$app->db->createCommand()->update('host',['bedrooms' => $bedroom ,'beds' => $beds,'bathrooms' => $bathrooms ],'hostid ='.$id)->execute();
			
				$update = "update host set bedrooms = '".$bedroom."',
										beds = '".$beds."',
										bathrooms = '".$bathrooms."',
										bed_type  = '".$bedtype."',
										landmark_location = '".$finalname."',
										landmark_distance = '".$finaldistace."'
										where hostid = '".$_POST['hidden_id']."' and user_id = '".$_SESSION['id']."'";
				$updateQuery = Yii::$app->db->createCommand($update)->execute();						
				//exit;
				return $this->redirect('location/'.$_POST['hidden_id'].'');
			 }
			 
			 	}
				
	
	/*public function actionLocation()
	{
		$id = Yii::$app->request->get('id');
		if (!empty($id))
		{
			  $selectID = "select * from host where hostid = '".$id."'";
			  $userdata = Yii::$app->db->createCommand($selectID)->queryAll();
	  		  return $this->render('location',['lastInsertid'=>$userdata]); 		
		}

			 if (!empty($_POST['lat']))
			 {				  
				$latitude = Yii::$app->request->post('lat');
				
				$longtitute = Yii::$app->request->post('lon');
				
			//	$update_roomandbeds = Yii::$app->db->createCommand()->update('host',['bedrooms' => $bedroom ,'beds' => $beds,'bathrooms' => $bathrooms ],'hostid ='.$id)->execute();
			
				 $update = "update host set latitude = '".$latitude."',
										longitude = '".$longtitute."'
 										where hostid = '".$_POST['hidden_id']."' and user_id = '".$_SESSION['id']."'";
				$updateQuery = Yii::$app->db->createCommand($update)->execute();						
				//exit;
				return $this->redirect('locationmap/'.$_POST['hidden_id'].'');
			 }

	}	*/
	
	public function actionLocation()
	{
		$id = Yii::$app->request->get('id');
		if (!empty($id))
		{
			  $selectID = "select * from host where hostid = '".$id."'";
			  $userdata = Yii::$app->db->createCommand($selectID)->queryAll();
	  		  return $this->render('location',['lastInsertid'=>$userdata]); 		
		}

			 if (!empty($_POST['lat']))
			 {				  
				$latitude = Yii::$app->request->post('lat');
				$longtitute = Yii::$app->request->post('lon');
				
			//	$update_roomandbeds = Yii::$app->db->createCommand()->update('host',['bedrooms' => $bedroom ,'beds' => $beds,'bathrooms' => $bathrooms ],'hostid ='.$id)->execute();
			
				 $update = "update host set latitude = '".$latitude."',
										longitude = '".$longtitute."'
 										where hostid = '".$_POST['hidden_id']."' and user_id = '".$_SESSION['id']."'";
				$updateQuery = Yii::$app->db->createCommand($update)->execute();					
				//exit;
				 return $this->redirect('locationmap/'.$_POST['hidden_id'].'');
			 }

	}
	
	public function actionLocationmap()
	{
		
		$id = Yii::$app->request->get('id');
		if (!empty($id)) {
			
				  $selectID = "select * from host where hostid = '".$id."'";
				  $userdata = Yii::$app->db->createCommand($selectID)->queryAll();
				  return $this->render('locationmap',['lastInsertid'=>$userdata]); 		
			}
			 if (!empty($_POST['address'])){
				 
				 
				 $url = $_POST['address'];
				 
 				   $country = substr($url, strrpos($url, ',') + 1); 
				 $r = explode(',',@$_POST['address']);
//echo'<pre>';print_r($r); die;
				 $sta = @$r[count($r)-2];
				 
				 
				 
				 if (isset($r[count($r)-3]))
				 {
				 	 $city = $r[count($r)-3];
				 }
				 else 
				 {
					$city = ''; 
				 }
				 
				   $mycountry = $url; 
                  $findstring   = 'United Arab Emirates';
                  @$pos = strpos($mycountry,$findstring);
				 if($pos !== false)
				 {
					  $country=$findstring; 
				 }
				 else
				 {
					 //echo 9; die;
				 $country = substr($url, strrpos($url, ',') + 1);  	 
				 }
		  	 //echo $city = $r[count($r)-3];
				$state = $sta;
					 
  				$latitude = Yii::$app->request->post('latitude');
				
				$longtitute = Yii::$app->request->post('longitude');
				$zipcode = Yii::$app->request->post('zipcode');

				$address  = Yii::$app->request->post('address');
 				
			//	$update_roomandbeds = Yii::$app->db->createCommand()->update('host',['bedrooms' => $bedroom ,'beds' => $beds,'bathrooms' => $bathrooms ],'hostid ='.$id)->execute();
			
				 $update = "update host set latitude = '".$latitude."',
										   longitude = '".$longtitute."',
										    zipcode = '".$zipcode."',
										   address  = '".mysql_real_escape_string($address)."',
										   country = '".mysql_real_escape_string($country)."',
										   state = '".mysql_real_escape_string($state)."',
										   city  = '".mysql_real_escape_string($city)."'
 										   where hostid = '".$_POST['hidden_id']."' and user_id = '".$_SESSION['id']."'"; 
				$updateQuery = Yii::$app->db->createCommand($update)->execute();						
				//exit;
				return $this->redirect('mylisting/'.$_POST['hidden_id'].'');
			 }
 	
	}
	
	public function actionLocationmap_old()
	{
		$id = Yii::$app->request->get('id');
		if (!empty($id)) {
			
				  $selectID = "select * from host where hostid = '".$id."'";
				  $userdata = Yii::$app->db->createCommand($selectID)->queryAll();
				  return $this->render('locationmap',['lastInsertid'=>$userdata]); 		
			}
			 if (!empty($_POST['address'])){
				 
				 
				  $url = $_POST['address'];
				  
				if (strpos($url,',') !== false)
				 {
					 $country = substr($url, strrpos($url, ',') + 1);
					$r = explode(',',@$_POST['address']);
				 
				 $sta = @$r[count($r)-2];
				 if (isset($r[count($r)-3]))
				 {
				 	 $city = $r[count($r)-3];
				 }
				 else 
				 {
					$city = ''; 
				 }
					 
					
				}
				else
				{
					$country =$_POST['address'];
					$city = ''; 
					$sta='';
					
				}
				  
				 
				 
				 
				 
				 
		  	 //echo $city = $r[count($r)-3];
				$state = $sta;
					 
  				$latitude = Yii::$app->request->post('latitude');
				
				$longtitute = Yii::$app->request->post('longitude');
				$zipcode = Yii::$app->request->post('zipcode');

				$address  = Yii::$app->request->post('address');
 				
			//	$update_roomandbeds = Yii::$app->db->createCommand()->update('host',['bedrooms' => $bedroom ,'beds' => $beds,'bathrooms' => $bathrooms ],'hostid ='.$id)->execute();
			
				 $update = "update host set latitude = '".$latitude."',
										   longitude = '".$longtitute."',
										    zipcode = '".$zipcode."',
										   address  = '".mysql_real_escape_string($address)."',
										   country = '".mysql_real_escape_string($country)."',
										   state = '".mysql_real_escape_string($state)."',
										   city  = '".mysql_real_escape_string($city)."'
 										   where hostid = '".$_POST['hidden_id']."' and user_id = '".$_SESSION['id']."'"; 
										   
				$updateQuery = Yii::$app->db->createCommand($update)->execute();						
				//exit;
				return $this->redirect('mylisting/'.$_POST['hidden_id'].'');
			 }
 	}
	
	public function actionMylisting()
	{
		$id = Yii::$app->request->get('id');
		if (!empty($id))
			{
			  $selectID = "select * from host where hostid = '".$id."'";
			  $userdata = Yii::$app->db->createCommand($selectID)->queryAll();
			  return $this->render('mylisting',['lastInsertid'=>$userdata]); 		
			}
	}
	
	public function actionBookingstart()
	 { 		
		$modelHost =  new Host();
		$modelUser =  new User();
		
		$request= Yii::$app->request->get();
		
		
	
		
		$modelUserData =  User::find()->where(['id' => $request['uid']])->one();
		
		$listid = $request['listingno'];
		$sql="select * from host where `hostid`='".$listid."' ";
		$getbookinglistdata= Host::findBySql($sql)->with('hostImage')->asArray()->all(); 
		
		
		
		
		$getCountry = $modelHost->getCountry();
		
		 return $this->render('bookingstart',['request'=> $request,'getbookinglistdata'=>$getbookinglistdata,'getCountry'=>$getCountry,'modelUserData'=>$modelUserData]);		 
	 }
	
	public function actionBookingrequest()
	{
		
		$response = '';
		$msg = '';
		$modelhgh_bookingtemp =  new Allrounder('hgh_bookingtemp');				
		
		$hostModel =  new Host();
		
		$lastInsertBookID = '';
		
		if(Yii::$app->request->post())
		{			
			$modelhgh_bookingtemp->f_date1 = Yii::$app->request->post('f_date1');
			$modelhgh_bookingtemp->f_date2 = Yii::$app->request->post('f_date2');
			
			$modelhgh_bookingtemp->fname1 = Yii::$app->request->post('fname1');
			$modelhgh_bookingtemp->lname1 = Yii::$app->request->post('lname1');
			
			if(isset($_REQUEST['surname1']))
			{
				$modelhgh_bookingtemp->surname1 = Yii::$app->request->post('surname1');
			
				//$modelhgh_bookingtemp->fname1 = Yii::$app->request->post('fname1');
				//$modelhgh_bookingtemp->lname1 = Yii::$app->request->post('lname1');
		
				$surnameArr = Yii::$app->request->post('surname');	
				
				if(!empty($surnameArr))
				{
					$surname =  implode(',',$surnameArr);			
					$modelhgh_bookingtemp->surname = $surname;
				}			
			
				$fnamearr = Yii::$app->request->post('fname');
			
				if(!empty($fnamearr))
				{
					$fname = implode(',',$fnamearr);			
					$modelhgh_bookingtemp->fname = $fname;
				}			
			
		$lname_arr = Yii::$app->request->post('lname');
			
				if(!empty($lname_arr))
				{
					$lname = implode(',',$lname_arr);			
					$modelhgh_bookingtemp->lname = $lname; 
				}		
			}		  
		  
			$modelhgh_bookingtemp->bookingDesc = mysql_real_escape_string(Yii::$app->request->post('bookingDesc'));
			
			$modelhgh_bookingtemp->paymentCountry = '';//Yii::$app->request->post('paymentCountry');
			
			$modelhgh_bookingtemp->paymentmethod = Yii::$app->request->post('paymentmethod');
			$modelhgh_bookingtemp->user_id = Yii::$app->request->post('uid');
			$modelhgh_bookingtemp->list_id = Yii::$app->request->post('listid');
		
		// Payment Info 
		
			$modelhgh_bookingtemp->ccn 			= Yii::$app->request->post('ccn');
			$modelhgh_bookingtemp->EXPDATE 		= Yii::$app->request->post('EXPDATE');
			$modelhgh_bookingtemp->CVV2 		= Yii::$app->request->post('CVV2');
			$modelhgh_bookingtemp->FIRSTNAME 	= Yii::$app->request->post('FIRSTNAME');
			$modelhgh_bookingtemp->LASTNAME 	= Yii::$app->request->post('LASTNAME');
			$modelhgh_bookingtemp->STREET 		= Yii::$app->request->post('STREET');
			$modelhgh_bookingtemp->CITY 		= Yii::$app->request->post('CITY');
			$modelhgh_bookingtemp->STATE 		= Yii::$app->request->post('STATE');
			$modelhgh_bookingtemp->ZIP 			= Yii::$app->request->post('ZIP');
			$modelhgh_bookingtemp->COUNTRYCODE 	= Yii::$app->request->post('COUNTRYCODE');		
			$modelhgh_bookingtemp->totalCharge 	= Yii::$app->request->post('totalCharge');		
			$modelhgh_bookingtemp->currency 	= Yii::$app->request->post('currency');				
			$modelhgh_bookingtemp->paymenttype 	= Yii::$app->request->post('paymentmethodfirst');				
			$modelhgh_bookingtemp->confiramtionno = Yii::$app->request->post('confiramtionno');				
			$modelhgh_bookingtemp->num_of_guest = Yii::$app->request->post('num_of_guest');	
			
			$modelhgh_bookingtemp->cleaningfee = Yii::$app->request->post('cleaningfee');				
			$modelhgh_bookingtemp->extraperson = Yii::$app->request->post('extraperson');				
			$modelhgh_bookingtemp->servicefee = Yii::$app->request->post('servicefee');				
			$modelhgh_bookingtemp->amount_per_night = Yii::$app->request->post('amount_per_night');				
			
			$modelhgh_bookingtemp->save();
			
			$lastInsertTempBookID =  Yii::$app->db->getLastInsertID();
			
			$postCurrency = Yii::$app->request->post('currency');
			
			$currencyCode 		  = 'USD';
			
			/*if($postCurrency!='INR')
			{
				$currencyCode = $postCurrency;
			}
			*/
			
			$totalCharge 		  = Yii::$app->request->post('totalCharge');				
			$currency			  = Yii::$app->request->post('currency');
			
			
			 $paymentmethodfirst =  Yii::$app->request->post('paymentmethodfirst');
			
		// Paypal Payment process
			
				$requestParams = array(
					'IPADDRESS' => $_SERVER['REMOTE_ADDR'],          // Get our IP Address
					'PAYMENTACTION' => 'Sale'
				);
				$creditCardDetails = array(
					'CREDITCARDTYPE' => Yii::$app->request->post('paymentmethod'),
					'ACCT' => Yii::$app->request->post('ccn'),
					'EXPDATE' => Yii::$app->request->post('EXPDATE'),          // Make sure this is without slashes (NOT in the format 07/2017 or 07-2017)
					'CVV2' => Yii::$app->request->post('CVV2')
				);
				$payerDetails = array(
					'FIRSTNAME' => Yii::$app->request->post('FIRSTNAME'),
					'LASTNAME' => Yii::$app->request->post('LASTNAME'),
					'COUNTRYCODE' => Yii::$app->request->post('COUNTRYCODE'),
					'STATE' => Yii::$app->request->post('STATE'),
					'CITY' => Yii::$app->request->post('CITY'),
					'STREET' => Yii::$app->request->post('STREET'),
					'ZIP' => Yii::$app->request->post('ZIP')
				);

				$orderParams = array(
					'AMT' => '1',               // This should be equal to ITEMAMT + SHIPPINGAMT
					//'ITEMAMT' => '496',
					//'SHIPPINGAMT' => '4',
					'CURRENCYCODE' => 'USD'       // USD for US Dollars
				);

				/*$item = array(
					'L_NAME0' => 'iPhone',
					'L_DESC0' => 'White iPhone, 16GB',
					'L_AMT0' => '496',
					'L_QTY0' => '1'
				);*/
					
				$paypal = new Paypalddp();	
				
				
			if($paymentmethodfirst=='paypal')
			{
				$paymentInfo =  array('lastInsertTempBookID'=>$lastInsertTempBookID,'currencyCode'=>$currencyCode,'amount'=>$totalCharge);
				
				$session = Yii::$app->session;
				
				$session->set('paymentInfo', $paymentInfo);
				
				$outputResponse = $paypal->SetExpressCheckout($paymentInfo);
				exit;
			}
			else			
			{				
				$response = $paypal -> request('DoDirectPayment',
					$requestParams + $creditCardDetails + $payerDetails + $orderParams 
				);
			
			//echo '<pre>';
			//print_r($response);
			//exit;
		
				
				if( is_array($response) && $response['ACK'] == 'Success') 
				{
					$transactionId = $response['TRANSACTIONID'];					
					//$modelhgh_bookingtemp->paymentStatus = $response['ACK'];
					//$modelhgh_bookingtemp->transactionId = $transactionId;
					
					// Insert into Booking Table
						
					$modelhgh_booking =  new Allrounder('hgh_booking');
					
					$modelhgh_booking->booking_confirmation_no = Yii::$app->request->post('confiramtionno');
					$modelhgh_booking->transaction_no = $transactionId;
					$modelhgh_booking->guest_id = "".Yii::$app->request->post('uid')."";
					$modelhgh_booking->list_id = "".Yii::$app->request->post('listid')."";
					$modelhgh_booking->msg_id = '0';
					$modelhgh_booking->booked_on_date = time();
					$modelhgh_booking->booked_date_in = Yii::$app->request->post('f_date1');
					$modelhgh_booking->booked_date_out = Yii::$app->request->post('f_date2');
					//$modelhgh_booking->no_of_guest = 1;
					$modelhgh_booking->payment_type = Yii::$app->request->post('paymentmethod');
					$modelhgh_booking->no_of_guest = Yii::$app->request->post('num_of_guest');
					$modelhgh_booking->payment_status = 'paid';
					$modelhgh_booking->datetime = date('Y-m-d H:i:s');
					$modelhgh_booking->status = '1';
					
					$modelhgh_booking->save();
					$lastInsertBookID =  Yii::$app->db->getLastInsertID();
					// success mail
						$uid =  Yii::$app->request->post('uid');
						$customer = User::find()->where(['id' => $uid])->one();						
			
					// Mail
			
			$listLink = 'http://hostguesthome.com/profile/view/'.Yii::$app->request->post('listid');			
			
			$msgsentTxt =  'Your HGH listing booking successfully done';	
					
			$msgsentTxt .= '<br/><br/> See your booked list  : <a target="_blank" href="'.$listLink.'"> '.$listLink.' </a>';
			
			$msgsentTxt .='<br/><br/> Your Booking Confirmation Number : '.Yii::$app->request->post('confiramtionno');
			$msgsentTxt .='<br/><br/> Your Transaction Id : '.$transactionId;
			
			$msgsentSubject =  'Your HGH listing booking successfully done.';	
			
			$heading = 'Thank you : HGH Booking Mail';
			
	$hostModel->messagemails(''.$customer->email.'',''.$customer->firstname.'',''.$msgsentTxt.'',''.$msgsentSubject.'',''.$heading.'','booking@hostguesthome.com');
					
					// end 
					
					
					
					//sending the payment confirmation on user's mobile
					$numbers=$customer->phone_verify;
					if(!empty($numbers))
					{
					$num_array = explode(',',$numbers);
					
					$mobile = end($num_array);
					$ProfileModel = new ProfileModel;
					 $text=$transactionId." is Your Transaction Id,Your HGH listing booking successfully done on HostGuestHome(HGH).";
				$text=urlencode($text);
				
						
				 $url="http://promo.smsfresh.co/api/sendmsg.php?user=".SMS_USERNAME."&pass=".SMS_PASSWORD."&sender=".SMS_SENDER_ID."&phone=".$mobile."&text=".$text."&priority=".SMS_PRIORITY."&stype=".SMS_TYPE."";
			  
				$responseCell = $ProfileModel->get_web_page($url);
				
					// end of msg working or sending payment confirmation
					}
					$msg = 'Payment successful';
					
					
									
				}
				else
				{					   
				   $msg = 'There was an error processing Request!';		
					
					echo '<pre>';
						print_r($response);					
					exit;
				}
			}
			// end 	
			
		// end payment info 
		
			$modelhgh_bookingtemp->save();
		}
		
		return $this->render('bookingconfirm',['response'=>$response,'msg'=>$msg]);
	}	

	
	public function actionConfirm()
	{	
		$paypal = new Paypalddp();	
		$hostModel =  new Host();
		
		if(isset($_REQUEST['token']))
		{
		$token = Yii::$app->request->get('token');
		$PayerID = Yii::$app->request->get('PayerID');
		
			$session = Yii::$app->session;				
			$sessPaymentInfo = $session->get('paymentInfo');			
			
			$lastInsertTempBookID = $sessPaymentInfo['lastInsertTempBookID'];
			$currencyCode 		  = $sessPaymentInfo['currencyCode'];
			$amount 			  = $sessPaymentInfo['amount'];	
		
		
			$ORDERTOTAL = $amount;
	
			$response = $paypal->confirmAction($token,$PayerID,$ORDERTOTAL);	
			//echo "<pre>";
	//print_r($response);exit;
		// TRANSACTIONTYPE	
		//PAYMENTSTATUS
		// TRANSACTIONID
		
		// ACK
		// L_SHORTMESSAGE0
			
		$ACK = $response['ACK'];
		$arrinfo = '';
		$bodyrep = '';
		
		if($ACK=='Failure')
		{
			 $L_SHORTMESSAGE0 = $response['L_SHORTMESSAGE0'];
			
			$msg = $L_SHORTMESSAGE0;
					
		}
		else
		{			
		
		 $tempUserData = 'SELECT * FROM hgh_bookingtemp where user_id="'.$_SESSION['id'].'" order by temp_id desc limit 1';
			  
				$tempData = Yii::$app->db->createCommand($tempUserData)->queryAll();
				//echo''; print_r($tempData); die;
				$confiramtionno   = $tempData[0]['confiramtionno'];
				$transactionId    = $response['TRANSACTIONID'];
				$CURRENCYCODE     = $response['CURRENCYCODE'];
				$TRANSACTIONTYPE  = $response['TRANSACTIONTYPE'];
				$uid 			  = $_SESSION['id'];
				$listid 		  = $tempData[0]['list_id'];
				$booked_on_date   = time();
				$msg_id 		  = 0;
				$f_date1 		  =  $tempData[0]['f_date1'];
				$f_date2 		  =  $tempData[0]['f_date2'];	
				
				$surname1 		  =  $tempData[0]['surname1'];
				$fname1 		  =  $tempData[0]['fname1'];
				$lname1 	      =  $tempData[0]['lname1'];
				$surname 	      =  $tempData[0]['surname'];
				$fname 		      =  $tempData[0]['fname'];
				$lname 			  =  $tempData[0]['lname'];
				$bookingDesc 	  =  $tempData[0]['bookingDesc'];
				$totalCharge 	  =  $tempData[0]['totalCharge'];				
				$no_of_guest 	  =  $tempData[0]['num_of_guest'];				
				
				
				$cleaningfee 	  =  $tempData[0]['cleaningfee'];
				$extraperson 	  =  $tempData[0]['extraperson'];
				$servicefee 	  =  $tempData[0]['servicefee'];				
				$amount_per_night 	  =  $tempData[0]['amount_per_night'];	
				
				
				if(!empty($surname1))
				{
					$explodeext 	=  explode(',',$surname1);	
					//$no_of_guest 	=  count($explodeext) + 1;
				}
				
				
				$paymenttype 	=  $tempData[0]['paymenttype'];
				$paymentmethod 	=  $tempData[0]['paymentmethod'];
				
				$payment_status	=  $response['PAYMENTSTATUS'];
				
				
				
				
				 $tempUserData1 = 'SELECT accommodates FROM host where hostid="'.$listid.'" ';
			  
				$tempData1 = Yii::$app->db->createCommand($tempUserData1)->queryAll();
				 //echo'<pre>'; print_r($tempData); 
				$limitguest= $tempData1[0]['accommodates'];
			   $modelhgh_booking =  new Allrounder('hgh_booking');
			   
			   
			   //echo'<pre>'; print_r($modelhgh_booking->booking_confirmation_no); die;
			   
			   
					
				$modelhgh_booking->booking_confirmation_no = $confiramtionno;
				$modelhgh_booking->transaction_no = $transactionId;
				$modelhgh_booking->guest_id = "".$uid."";
				$modelhgh_booking->list_id = "".$listid."";
				$modelhgh_booking->msg_id = $msg_id;
				$modelhgh_booking->booked_on_date = $booked_on_date;
				$modelhgh_booking->booked_date_in = $f_date1;
				$modelhgh_booking->booked_date_out = $f_date2;
				  $daylen = 60*60*24;
                 $date1 = $f_date1;
                 $date2 = $f_date2;

                 $total_days = (strtotime($date2)-strtotime($date1))/$daylen;
				 $modelhgh_booking->no_of_guest = $no_of_guest; 
				if($no_of_guest > $limitguest)
				{
					/*-----extra person get-----*/
					$extra_persn=$no_of_guest-$limitguest;
					 $extra_persn_with_all= $extra_persn*$extraperson*$total_days; 
					 //$extraperson= $extra_persn_with_all;
					 
					 //$extra_persn=$extra_persn_with_all;
					 //$modelhgh_booking->no_of_guest =  $extra_persn_with_all;	
						
				}
				
				
				$modelhgh_booking->payment_type = $paymentmethod;
				$modelhgh_booking->paymenttype = $paymenttype;
				
				$modelhgh_booking->payment_status = 'paid';
				$modelhgh_booking->datetime = date('Y-m-d H:i:s');
				$modelhgh_booking->status = 1; 
				
				$modelhgh_booking->surname1 = $surname1;
				$modelhgh_booking->fname1 = $fname1;
				$modelhgh_booking->lname1 = $lname1;
				$modelhgh_booking->surname = $surname;
				$modelhgh_booking->fname = $fname;
				$modelhgh_booking->lname = $lname;
				$modelhgh_booking->bookingDesc = $bookingDesc;				
				$modelhgh_booking->TRANSACTIONTYPE = $TRANSACTIONTYPE;				
				$modelhgh_booking->CURRENCYCODE = $CURRENCYCODE;				
				$modelhgh_booking->totalCharge = $totalCharge;	
				
				$modelhgh_booking->cleaningfee 	= $cleaningfee;
					if(!empty($extra_persn_with_all))
					{
                       // $extraperson='';						
						$extraperson= $extra_persn_with_all;		
						 $modelhgh_booking->extraperson = $extraperson;	
						
					}
					else
					{
						
					$modelhgh_booking->extraperson 		= $extraperson;	
					}	
				$modelhgh_booking->servicefee  		= $servicefee;				
				$modelhgh_booking->amount_per_night = $amount_per_night;				
					
			  $modelhgh_booking->save();
					
					
					// success mail
						$uid =  $_SESSION['id'];
						$customer = User::find()->where(['id' => $uid])->one();						
			
					// Mail
			
			//$listLink = Yii::$app->request->baseUrl.'/profile/view/'.$listid;			
			$listLink = HGH_URL.'/site/viewdetail/'.$listid;	
			$msgsentTxt =  'Your HGH listing booking successfully done';	
					
			$msgsentTxt .= '<br/><br/> See your booked list  : <a target="_blank" href="'.$listLink.'"> '.$listLink.' </a>';
			
			$msgsentTxt .='<br/><br/> Your Booking Confirmation Number : '.$confiramtionno;
			$msgsentTxt .='<br/><br/> Your Transaction Id : '.$transactionId;
			
			$msgsentSubject =  'Your HGH listing booking successfully done.';	
			
			$heading = 'Thank you : HGH Booking Mail';
			
			//print_r($customer->email);
			
	//$hostModel->messagemails(''.$customer->email.'',''.$customer->firstname.'',''.$msgsentTxt.'',''.$msgsentSubject.'',''.$heading.'','booking@hostguesthome.com');
			
	if(!empty($customer->profile_img))
	{
		$profilepic = $customer->profile_img;
	}
	else
	{
		$profilepic = 'profile.jpg';
	}
	
	$arrinfo =  array('listLink'=>$listLink,
					  'confiramtionno'=>$confiramtionno,
					  'transactionId'=>$transactionId,
					  'profilepic'=>$profilepic,
					  'listid'=>$listid,
					  'f_date1'=>$f_date1,
					  'f_date2'=>$f_date2,
					  'no_of_guest'=>$no_of_guest,
					  'totalCharge'=>$totalCharge,
					  'cleaningfee'=>$cleaningfee,
					  'extraperson'=>$extraperson,
					  'servicefee'=>$servicefee,
					  'amount_per_night'=>$amount_per_night,
					  'CURRENCYCODE'=>$CURRENCYCODE,
					  );
					  
					
		
	$bodyrep = $hostModel->bookingconfirmmail(''.$customer->email.'',''.$customer->firstname.'',''.$msgsentTxt.'',''.$msgsentSubject.'',''.$heading.'','booking@hostguesthome.com',$arrinfo);
		
					// end 
					
					//sending the payment confirmation on user's mobile
					$numbers=$customer->phone_verify;
					$num_array = explode(',',$numbers);
					
					$mobile = end($num_array);
					$ProfileModel = new ProfileModel;
					 $text=$transactionId." is Your Transaction Id,Your HGH listing booking successfully done on HostGuestHome(HGH).";
				$text=urlencode($text);
				
						
				 $url="http://promo.smsfresh.co/api/sendmsg.php?user=".SMS_USERNAME."&pass=".SMS_PASSWORD."&sender=".SMS_SENDER_ID."&phone=".$mobile."&text=".$text."&priority=".SMS_PRIORITY."&stype=".SMS_TYPE."";
			  
				$responseCell = $ProfileModel->get_web_page($url);
				
				
				$msg = 'Payment successful';
		}
				
		return $this->render('bookingconfirm',['response'=>$response,'msg'=>$msg,'arrinfo'=>$arrinfo,'bodyrep'=>$bodyrep]);
		}
	}
	
	public function actionBooked()
	{
		$bkid = Yii::$app->request->get('bkid');
	  
		$bookingModel =  new Booking();  
		$response = $bookingModel->getBookedDataById($bkid);		  
		  
		$msg = '';
		  
		return $this->render('booked',['response'=>$response,'msg'=>$msg]);
	}
	
	public function actionGetpdfinvoice()
	{
		
		$html = '';
		$data = ob_get_clean();		
		
		
		$html2pdf = new Yii::$app->HTML2PDF('P', 'A4', 'en');		
		
			//$html = $homepage;
		try
				{
					//$html2pdf = Yii::$app->HTML2PDF('P', 'A4', 'en');
					//$html2pdf->setModeDebug();
					$html2pdf->setDefaultFont('Arial');
					//$html2pdf->pdf->IncludeJS(“print(true);”);           // To open Printer dialog box
					$html2pdf->writeHTML($html);
					//$html2pdf->Output("name12.pdf",'D');                   // To download PDF
					$html2pdf->Output("name1.pdf","I");                  // To display PDF in browser
					//$html2pdf->Output("pdf/name2.pdf",'F');              // To save the pdf to a specific folder
					//where name2.pdf will get saved in “pdf” folder
					//echo '<pre>';
					//print_r($html2pdf);
					exit;
				}
				catch(HTML2PDF_exception $e) 
				{
					echo $e;
					exit;
				}
		exit;
		
	}
	
	public function actionInvoice()
	{					
		$bookingModel =  new Booking();
		$bkid = Yii::$app->request->get('bkid');
		
		$response = $bookingModel->getBookedDataById($bkid);
		
		return $this->renderPartial('invoice',['response' => $response]);
	}
	
	
	public function actionContacttohost()
	{
		if(isset($_REQUEST['list_id']) && !empty($_REQUEST['list_id']))
		{		
			$hostModel =  new Host();
		
			$checkin 		= '';
			$checkout 		= '';			
			$listingid 		= '';				
			$no_of_guest 	= '';	
			
			$user_id    =  $_REQUEST['user_id'];
			$host_id    =  $_REQUEST['host_id'];
			
			$customer = User::find()->where(['id' => $user_id])->one();			
			$hostData = User::find()->where(['id' => $host_id])->one();			
 	
			$msgTXT = '';
			
			$msgTXT .=  'Hi! '.$customer->firstname.' has sent you a contact request';
            
			// Mail
			
			$msgsentTxt =  ''.$customer->firstname.' has sent you a contact request';	
			$msgsentSubject =  ''.$customer->firstname.' has sent you a contact request.';	
			
			$heading = 'Contact to host';
			
	$hostModel->messagemails(''.$hostData->email.'',''.$hostData->firstname.'',''.$msgsentTxt.'',''.$msgsentSubject.'',''.$heading.'','messagebox@hostguesthome.com');
			
			// end mail
			
			//$customers = Host::findOne($listingid);
			//$customers = Host::find($listingid)->asArray()->one();			
			$messageModel = new Message();
			
			$messageModel->host_id  = $host_id;
			$messageModel->guest_id = $user_id;
			$messageModel->listing_id = $_REQUEST['list_id'];			
			$messageModel->checkin = 0;
			$messageModel->chekout = 0;			
			$messageModel->no_of_guest = 0;			
			$messageModel->message_txt = $msgTXT;
			$messageModel->msg_type = 'contacttohost';
			$messageModel->msg_status = '0';
			$messageModel->status = '1';
			$messageModel->read_status = '0';
			$messageModel->datetime = date('Y-m-d H:i:s');			
			$messageModel->save();			
			echo   $lastInsertID =  Yii::$app->db->getLastInsertID();
			exit;
			
		}
		else
		{
			echo '0';
			exit;
		}
	}
	
	public function actionTripinviterequest()
	{
		$hostModel =  new Host();
		
		if(isset($_REQUEST['trip_id']))
		{
			$checkin 		= '';
			$checkout 		= '';			
			$listingid 		= '';				
			$no_of_guest 	= '';	
			
			$list_user  =  $_REQUEST['list_user'];
			$host_id    =  $_REQUEST['host_id'];
			
			$customer = User::find()->where(['id' => $host_id])->one();			
			$hostData = User::find()->where(['id' => $list_user])->one();			
			
			$msgTXT = '';
			
			$msgTXT .=  'Hi! '.$customer->firstname.' has sent you a Invitation Request for listings';	
			
			// Mail
			
			$listLink = 'http://hostguesthome.com/profile/view/'.$host_id;			
			
			$msgsentTxt =  ''.$customer->firstname.' has sent you a Invitation Request for listings';	
					
			$msgsentTxt .= '<br/><br/> Please check listing on '.$customer->firstname.' profile : <a target="_blank" href="'.$listLink.'"> '.$listLink.' </a>';
			
			$msgsentSubject =  ''.$customer->firstname.' has sent you a Invitation Request for listings.';	
			
			$heading = 'Trip Invitation';
			
	$hostModel->messagemails(''.$hostData->email.'',''.$hostData->firstname.'',''.$msgsentTxt.'',''.$msgsentSubject.'',''.$heading.'','messagebox@hostguesthome.com');
			
			// end mail
			
			//$customers = Host::findOne($listingid);
			//$customers = Host::find($listingid)->asArray()->one();			
			$messageModel = new Message();
			
			$messageModel->host_id  = $host_id;
			$messageModel->guest_id = $list_user;
			$messageModel->listing_id = 0;			
			$messageModel->checkin = 0;
			$messageModel->chekout = 0;			
			$messageModel->no_of_guest = 0;			
			$messageModel->message_txt = $msgTXT;
			$messageModel->msg_type = 'tripinvitation';
			$messageModel->msg_status = '0';
			$messageModel->status = '1';
			$messageModel->datetime = date('Y-m-d H:i:s');			
			$messageModel->trip_id = $_REQUEST['trip_id'];			
			$messageModel->save();			
			echo '1';
			exit;			
		}
	}
	
	public function actionConfirmlistrequest()
	{
		$hostModel =  new Host();
	
		if(isset($_POST['msg_id']))
		{
			$msg_id = $_POST['msg_id'];
			$confirmation_no = $msg_id.'_'.time();
			$msg_id = Yii::$app->request->post('msg_id');	

			$str =  'update hgh_message set confirmation_no = "'.$confirmation_no.'" where msg_id ="'.$msg_id.'"';
			Yii::$app->db->createCommand($str)->execute();
	
		// Mail
		
			$messageData = Message::find()->where(['msg_id' => $msg_id])->one();
			
			$host_id  		 = $messageData->host_id;
			$guest_id 		 = $messageData->guest_id;
			$confirmation_no1 = $messageData->confirmation_no;
			$listing_id 	 = $messageData->listing_id;
			
			
			$guestData = User::find()->where(['id' => $guest_id])->one();
			$hostData = User::find()->where(['id' => $host_id])->one();
		
		$confirmationLink = HGH_URL.'/profile/message?mid='.$msg_id.'&host='.$host_id.'&guest='.$guest_id.'&confirmation='.$confirmation_no1;
		
		$listLink = HGH_URL.'/site/viewdetail/'.$listing_id;
		
		$msgsentTxt = 'Hi! '.$hostData->firstname.' has confirmed your reservation request. You can chat with the host and ask any questions regarding your stay, amenities, weather and so on. Now you can book your request <a href="'.$confirmationLink.'">Book Now</a>';
		
		$msgsentTxt .= '<br/><br/> Your Confirmation no:'.$confirmation_no1;
		
		$msgsentTxt .= '<br/><br/> Your requested list link is <a target="_blank" href="'.$listLink.'" > Here </a>';
		
		$msgsentTxt .= '<br/><br/> You want to conversion with '.$hostData->firstname.'   <a href="'.$confirmationLink.'" > click here </a>:';
		
		$msgsentSubject = 'Reservation Request Confirmation';
		$heading = 'Reservation Request Confirmation';
	
// Map code	
// https://maps.googleapis.com/maps/api/staticmap?zoom=13&size=600x300&maptype=roadmap&markers=color:red%7Clabel:C%7C40.718217,-73.998284
	
	
	
	
	$hostModel->messagemails(''.$guestData->email.'',''.$guestData->firstname.'',''.$msgsentTxt.'',''.$msgsentSubject.'',''.$heading.'','automatic@hostguesthome.com');
			
			// end mail	
			
 return $this->redirect(''.Yii::$app->request->baseUrl.'/profile/message?mid='.$msg_id.'&host='.$host_id.'&guest='.$guest_id.'&confirmation='.$confirmation_no1.'');
			
			echo '1';
			
			exit;
		}
	}
	
	 public function actionListrequest()
	{
		$messageModel = new Message();
		$hostModel = new Host();
		
		if(!empty($_SESSION['id']))
		{
			// Aceept request
			
			if(isset($_REQUEST['msg_status']))
			{
				$msg_status = Yii::$app->request->post('msg_status');				
				
				$msg_id = Yii::$app->request->post('msgid');
				$retext = Yii::$app->request->post('retext');
				
				if($retext=='accept')
				{
		
		 $str =  'update hgh_message set msg_status = "1", status="1" where msg_id ="'.$msg_id.'"';
		Yii::$app->db->createCommand($str)->execute();
		
				// Mail
			$messageData = Message::find()->where(['msg_id' => $msg_id])->one();
			
			$host_id  = $messageData->host_id;
			$guest_id = $messageData->guest_id;
			$confirmation_no = $messageData->confirmation_no;
			$listing_id = $messageData->listing_id;
			
			$guestData = User::find()->where(['id' => $guest_id])->one();
			$hostData = User::find()->where(['id' => $host_id])->one();
		
		$confirmationLink = ''.HGH_URL.'/profile/message?mid='.$msg_id.'&host='.$host_id.'&guest='.$guest_id.'&confirmation=';
		
		$listLink = HGH_URL.'/site/viewdetail/'.$listing_id;
		
		$msgsentTxt = 'Hi! '.$hostData->firstname.' has accepted your reservation request. You can chat with the host and ask any questions regarding your stay, amenities, weather and so on. If you and the host come to an agreement, you will be able to book the space when host clicks on CONFIRM RESERVATION. Good luck!';
		
		//$msgsentTxt .= '<br/><br/> Your Confirmation no:'.$confirmation_no;
		
		$msgsentTxt .= '<br/><br/> Your requested list link is <a target="_blank" href="'.$listLink.'" > Here </a>';
		
		$msgsentTxt .= '<br/><br/> You want to conversion with '.$hostData->firstname.'   <a href="'.$confirmationLink.'" > click here </a>:';
		
		$msgsentSubject = 'Reservation Request Confirmation';
		$heading = 'Reservation Request Confirmation';
	
	$hostModel->messagemails(''.$guestData->email.'',''.$guestData->firstname.'',''.$msgsentTxt.'',''.$msgsentSubject.'',''.$heading.'','automatic@hostguesthome.com');
			
			// end mail
				
				}
				else
				{
		 $str =  'update hgh_message set msg_status = "1", status="0" where msg_id ="'.$msg_id.'"';
		 
		Yii::$app->db->createCommand($str)->execute();				
				}
				
				echo $retext;
				
				exit;
			}
			
			// end 
		
		
		  if(isset($_REQUEST['checkin']))
		  {
			$checkin 		= $_REQUEST['checkin'];
			$checkout 		= $_REQUEST['checkout'];			
			$listingid 		= $_REQUEST['listingid'];			// HOST list	
			$no_of_guest 	= $_REQUEST['no_of_guest'];				
			
			//$customers = Host::findOne($listingid);
			//$customers = Host::find($listingid)->asArray()->one();
			$hostModel = new Host();
			$customer = Host::find()->where(['hostid' => $listingid])->with('user')->one();
			
			$firstname = $customer->user->firstname;
			$host_user_id    =  $customer->user_id;		//HOST id 	
			$guest_user_id   =  $_SESSION['id'];		//GUEST id 	
			
			$msgTXT = '';
			
			$msgTXT .=  'Hi! '.$_SESSION['firstname'].' has sent you a Reservation Request';						
			
			
			// Mail
			$hostData = User::find()->where(['id' => $host_user_id])->one();
			$guestData = User::find()->where(['id' => $guest_user_id])->one();
			
			$listLink = HGH_URL.'/site/viewdetail/'.$listingid;
			$msgsentTxt =  'I really like your listing and think that it is perfect for my stay. I like to reserve the room from '.$checkin.' until '.$checkout.' with '.$no_of_guest.' members. Please let me know. ';
			$msgsentTxt .= '<br/><br/> Listing Link : <a target="_blank" href="'.$listLink.'"> '.$listLink.' </a>';
			$msgsentSubject =  $msgTXT;	
			$heading = 'Reservation Request';
			//send mail to host	
$hostModel->messagemails(''.$hostData->email.'',''.$hostData->firstname.'',''.$msgsentTxt.'',''.$msgsentSubject.'',''.$heading.'');
	
			//send mail to guest
			$guestMessage = 'You have sent a Reservation Request to '.$hostData->firstname.' Please allow 24 hours to the Host to respond. If you don’t hear back from the HOST or if the HOST declines your request, please consider searching another listing. Thank you!';
			
			$guestMessage .= '<br/><br/> Listing Link : <a target="_blank" href="'.$listLink.'"> '.$listLink.' </a>';
			
			$msgsentSubject = 'Reservation Request Notification';
			
			$heading = 'Reservation Request Notification';
			$sentFrom = 'response@hostguesthome.com';
$hostModel->messagemails(''.$guestData->email.'',''.$guestData->firstname.'',''.$guestMessage.'',''.$msgsentSubject.'',''.$heading.'',''.$sentFrom.'');

			// end mail			

			$messageModel->host_id  = $host_user_id;
			$messageModel->guest_id = $_SESSION['id'];
			$messageModel->listing_id = $listingid;			
			$messageModel->checkin = $checkin;
			$messageModel->chekout = $checkout;			
			$messageModel->no_of_guest = $no_of_guest;
			$messageModel->message_txt = $msgTXT;
			$messageModel->msg_type = 'bookingrequest';
			$messageModel->msg_status = '0';
			$messageModel->status = '0';
			$messageModel->datetime = date('Y-m-d H:i:s');
			
			$messageModel->save();
			
			echo '1';
		  }	  
		  
		}
		else
		{
		echo '0';
		}
	}
	
	public function actionStartchat()
	{
		//$messageModel = new Message();
		$hostModel = new Host();		
		$messagechatModelnew = new Messagechat();		
		
		if(isset($_REQUEST['msgtext']) && !empty($_REQUEST['msgtext']))
		{
			$msg_id   = Yii::$app->request->post('msg_id');
			
			$guest_id = Yii::$app->request->post('receiver_id');
			$host_id  = Yii::$app->request->post('sender_id');			
			
			$type     = Yii::$app->request->post('type');
			$msgtext  = trim(Yii::$app->request->post('msgtext'));
			
			if($_SESSION['id']==$guest_id)
			{
				$receiver_id = $host_id;
			}
			else if($_SESSION['id']==$host_id)
			{
				$receiver_id = $guest_id;
			}
			
			$messagechatModelnew->msg_id  = $msg_id;
			$messagechatModelnew->sender_id = $_SESSION['id'];    //$host_id;
			$messagechatModelnew->receiver_id = $receiver_id;     //$guest_id;	
			$messagechatModelnew->chat_txt = ''.$msgtext.'';
			$messagechatModelnew->timestr = time();
			$messagechatModelnew->status = '1';	
			
			$messagechatModelnew->save();
			
			echo '1';
			exit;
		}
		else
		{
			echo '0';
			exit;
		}
		
	}


	public function actionDeletehostdetails()
	{
	 
			//$ProfileModel = new ProfileModel;
			 
		
			 $id = $_POST['hostID']; 
			 
			//$DelId = $_GET['id'];
			 
			   $delete = "delete from host where hostid = '".$id."'";
			  $delquery  = Yii::$app->db->createCommand($delete)->execute();
			
				if ($delquery)
				{
					$deleteIMg = "delete from host_image where hostid = '".$id."'";
					$imaquery  = Yii::$app->db->createCommand($deleteIMg)->execute(); 
					
					if($imaquery)
					{
						echo "1"; exit;//return 'deleted';
					}
					else 
					{
						echo 2;die;
					}
				 
			 }
	
	}

	public function actionDeleteimage()
	{
		$id  =  $_POST['hostID'];
		
			$deletImage = "delete from host_image where id = '".$id."'";
			$queryImage = Yii::$app->db->createCommand($deletImage)->execute(); 
						
			if ($queryImage)
			{
				//$s3->deleteObject('nanoweb','hostguesthome/uploadedfile/hostImages/'.$queryImage['images']);	
				echo '1';
			}
			 else
			{
				echo '2';
			}
	}
	
	public function actionRefundpaypal()
	{
		$bookingModel =  new Booking();
		$hostModel 	  =  new Host();
		
			$transaction_no = Yii::$app->request->post('transaction_no');
			$bookingid 		= Yii::$app->request->post('bookingid');
			$amount 		= Yii::$app->request->post('amount');
			$memo 			= Yii::$app->request->post('memo');
			$currencyID 	= Yii::$app->request->post('currencyID');
			
			
					$transactionID  = urlencode($transaction_no);
					$refundType 	= urlencode('Partial');   //urlencode('Full');  // or 'Partial'
					$amount ;                          // required if Partial.
					$memo ;                            // required if Partial.
					$currencyID = urlencode($currencyID);    // or other currency ('GBP', 'EUR', 'JPY', 'CAD', 'AUD')

					// Add request-specific fields to the request string.

					$nvpStr = "&TRANSACTIONID=$transactionID&REFUNDTYPE=$refundType&CURRENCYCODE=$currencyID";

					if(isset($memo)) {
					$nvpStr .= "&NOTE=$memo";
					}

					if(strcasecmp($refundType, 'Partial') == 0)
					{
						if(!isset($amount))
						{
							exit('Partial Refund Amount is not specified.');
						}
						else 
						{
							$nvpStr = $nvpStr."&AMT=$amount";
						}

						if(!isset($memo))
						{
							exit('Partial Refund Memo is not specified.');
						}
					}

					// Execute the API operation; see the PPHttpPost function above.

					$env = 'sandbox';
					$httpParsedResponseAr = $bookingModel->PPHttpPost('RefundTransaction', $nvpStr,$env);

					if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) 
					{
					
					$REFUNDTRANSACTIONID = $httpParsedResponseAr['REFUNDTRANSACTIONID'];
					$TOTALREFUNDEDAMOUNT = urldecode($httpParsedResponseAr['TOTALREFUNDEDAMOUNT']);
					$CORRELATIONID = $httpParsedResponseAr['CORRELATIONID'];
					$REFUNDSTATUS = $httpParsedResponseAr['REFUNDSTATUS'];
					$PENDINGREASON = $httpParsedResponseAr['PENDINGREASON'];
					$Refund_ACK = $httpParsedResponseAr['ACK'];
					
					$update = "update hgh_booking set REFUNDTRANSACTIONID = '".$REFUNDTRANSACTIONID."',TOTALREFUNDEDAMOUNT='".$TOTALREFUNDEDAMOUNT."' ,CORRELATIONID = '".$CORRELATIONID."' , REFUNDSTATUS = '".$REFUNDSTATUS."' , PENDINGREASON='".$PENDINGREASON."' , Refund_ACK = '".$Refund_ACK."' where booking_id = ".$bookingid;
					
					  Yii::$app->db->createCommand($update)->execute();
					
					// send mail
					
					$sentFrom = 'automatic@hostguesthome.com';			
					
					$subjetct = 'HGH:Your Reservation Cancelled';				
					
					$maildata = $bookingModel->getBookedDataById($bookingid);			
		
					
					$booked_date_in  = $maildata->booked_date_in;
					$booked_date_out = $maildata->booked_date_out;
					$fname1 		 = $maildata->fname1;
					
					// host data
					$title = $maildata['host']->title;
					$address 	= $maildata['host']->address;
					// end
					
					// user
					$emailTo 	= $maildata['user']->email;
					$firstname 	= $maildata['user']->firstname;					
					
	$msgsentTxt  = '';
	
	$msgsentTxt .= 'You recently cancelled your reservation at '.date('d M y').' Title of Listing '.$title.' in '.$address.' from '.$booked_date_in.' to '.$booked_date_out.'. <br/>';
	
	$msgsentTxt .= 'We`ve refunded '.$currencyID.' '.$amount.' to the payment method you used to book this reservation, in accordance with the host`s cancellation policy. <br/>';
	
	$msgsentTxt .= 'Depending on your  HostGuestHome payment method, it may take a few business days for this refund to reflect in your account. <br/>';	
	
	$msgsentTxt .= '<a href="'.HGH_URL.'"><div style="float:left; margin:5px 0 0 0; border-radius:5px; font-size:13px; cursor:pointer; background:#ff5a5f; padding:8px 10px 8px 10px; color:#fff;
font-family:Arial, Helvetica, sans-serif;">Click on View Receipt</div></a> <br/>';
					
					
					
					$msgsentSubject = 'HGH:Your Reservation Cancelled';
					$heading 		= 'Your Reservation Cancelled';
	
	$hostModel->messagemails(''.$emailTo.'',''.$firstname.'',''.$msgsentTxt.'',''.$msgsentSubject.'',''.$heading.'','automatic@hostguesthome.com');
					
					// end
					
					
						echo 'Refund Completed Successfully';
					
						exit;
						//exit('Refund Completed Successfully: '.print_r($httpParsedResponseAr, true));				
					
					} else  
					{
						exit('RefundTransaction failed: ' . print_r($httpParsedResponseAr, true));
					}
				
				exit;
		//return $this->render('refundpaypal',['transaction_no'=>$transaction_no,'bookingid'=>$bookingid]);
	}
	
	public function actionCancel()
	{
		echo '<pre>';
		echo print_r($_REQUEST);
		exit;
	}
	
	public function actionAjaxtest()
	{
		$hostUserImagedetail = Booking::find()->where(['booking_id' => 93])->with('host')->with('user')->all();
		
		echo '<pre>';
		
			print_r($hostUserImagedetail);
			
		exit;
		echo $data = '6';
		exit;
	}
}
