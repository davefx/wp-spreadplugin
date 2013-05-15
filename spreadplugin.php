<?php
/**
 * Plugin Name: WP-Spreadplugin
 * Plugin URI: http://wordpress.org/extend/plugins/wp-spreadplugin/
 * Description: This plugin uses the Spreadshirt API to list articles and let your customers order articles of your Spreadshirt shop using Spreadshirt order process.
 * Version: 2.8.1
 * Author: Thimo Grauerholz
 * Author URI: http://www.pr3ss-play.de
 */


/**
 * Avoid direct calls to this file
 */
if(!function_exists('add_action')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');

	exit();
}



/**
 * WP_Spreadplugin class
 */
if(!class_exists('WP_Spreadplugin')) {
	class WP_Spreadplugin {
		private $stringTextdomain = 'spreadplugin';
		private static $shopId;
		private static $apiUrl;
		private static $shopDisplay;
		private static $shopLocale;
		private static $shopLimit;
		private static $shopApi;
		private static $shopSecret;
		private static $shopCategoryId;
		private static $shopSocialEnabled;
		private static $shopLinkEnabled;
		private static $shopProductCategory;
		private static $shopProductSubCategory;
		private static $shopArticleSort;
		private static $shopLinkTarget;
		private static $shopCheckoutIframe;
		private static $shopDesignerShopId;
		private static $shopDesignsBackground;
		private static $shopImgSize;
		private static $shopShowDescription;
		private static $shopShowExtendPrice;
		private static $shopZoomImageBackgroundColor;
		public static $shopArticleSortOptions = array(
				'name',
				'price',
				'recent',
				'weight'
		);
		public $defaultOptions = array(
				'shop_id' => '',
				'shop_locale' => '',
				'shop_api' => '',
				'shop_source' => '',
				'shop_secret' => '',
				'shop_limit' => '',
				'shop_category' => '',
				'shop_subcategory' => '',
				'shop_social' => '',
				'shop_enablelink' => '',
				'shop_productcategory' => '',
				'shop_sortby' => '',
				'shop_linktarget' => '',
				'shop_checkoutiframe' => '',
				'shop_designershop' => '',
				'shop_display' => '',
				'shop_designsbackground' => '',
				'shop_showdescription' => '',
				'shop_imagesize' => '',
				'shop_showextendprice' => '',
				'shop_zoomimagebackground' => ''
		);
		private static $shopCache = 8760; // Shop article cache in hours 24*365 => 1 year

		public function WP_Spreadplugin() {
			WP_Spreadplugin::__construct();
		}

		public function __construct() {
			add_action('init', array($this,'plugin_init'));
			add_action('init', array($this,'startSession'), 1);
			add_action('wp_logout', array($this,'endSession'));
			add_action('wp_login', array($this,'endSession'));

			add_shortcode('spreadplugin', array($this,'Spreadplugin'));

			add_action('wp_footer', array($this,'loadScripts'));

			// Ajax actions
			add_action('wp_ajax_nopriv_myAjax',array($this,'doAjax'));
			add_action('wp_ajax_myAjax',array($this,'doAjax'));
			add_action('wp_ajax_regenCache',array($this,'doRegenerateCache'));

			// Scrolling
			wp_register_script('infinite_scroll', plugins_url('/js/jquery.infinitescroll.min.js', __FILE__),array('jquery'));
			wp_enqueue_script('infinite_scroll');
				
			// Fancybox
			wp_register_script('fancy_box', plugins_url('/js/jquery.fancybox.pack.js', __FILE__),array('jquery'));
			wp_enqueue_script('fancy_box');
			
			// Zoom
			wp_register_script('zoom', plugins_url('/js/jquery.elevateZoom-2.5.5.min.js', __FILE__),array('jquery'));
			wp_enqueue_script('zoom');

			// Respects SSL, Style.css is relative to the current file
			wp_register_style('spreadplugin', plugins_url('/css/spreadplugin.css', __FILE__));
			wp_enqueue_style('spreadplugin');
			wp_register_style('fancy_box_css', plugins_url('/css/jquery.fancybox.css', __FILE__));
			wp_enqueue_style('fancy_box_css');

			// admin check
			if(is_admin()){
				// Regenerate cache after activation of the plugin
				register_activation_hook(__FILE__, array($this, 'setRegenerateCacheQuery'));

				// add Admin menu
				add_action('admin_menu', array($this, 'addPluginPage'));
				// add Plugin settings link
				add_filter('plugin_action_links', array($this, 'addPluginSettingsLink'),10,2);
	
				// add color picker
				wp_enqueue_style('wp-color-picker');          
				wp_enqueue_script('wp-color-picker');  
			}

		}




		/**
		 * Initialize Plugin
		 */
		public function plugin_init() {
				
			// get translation
			if(function_exists('load_plugin_textdomain')) {
				load_plugin_textdomain($this->stringTextdomain, false, dirname(plugin_basename( __FILE__ )) . '/translation');
			}

		}


		/**
		 * Function Spreadplugin
		 *
		 * @return string article display
		 *
		 */
		public function Spreadplugin($atts) {
			global $paged;
				
			$articleCleanData = array(); // Array with article informations for sorting and filtering
			$articleData = array();
			$designsData = array();


			// get admin options (default option set on admin page)
			$conOp = $this->getAdminOptions();
				
			// shortcode overwrites admin options (default option set on admin page) if available
			$arrSc = shortcode_atts($this->defaultOptions, $atts);
				
			// replace options by shortcode if set
			if (!empty($arrSc)) {
				foreach ($arrSc as $key => $option) {
					if ($option != '') {
						$conOp[$key] = $option;
					}
				}
			}


			// setting vars
			self::$shopId = intval($conOp['shop_id']);
			self::$shopApi = $conOp['shop_api'];
			self::$shopSecret = $conOp['shop_secret'];
			self::$shopLimit = (empty($conOp['shop_limit'])?10:intval($conOp['shop_limit']));
			self::$shopLocale = (($conOp['shop_locale']=='' || $conOp['shop_locale']=='de_DE') && $conOp['shop_source']=='com'?'us_US':$conOp['shop_locale']); // Workaround for older versions of this plugin
			self::$apiUrl = $conOp['shop_source'];
			self::$shopCategoryId = intval($conOp['shop_category']);
			self::$shopSocialEnabled = intval($conOp['shop_social']);
			self::$shopLinkEnabled = intval($conOp['shop_enablelink']);
			self::$shopProductCategory = $conOp['shop_productcategory'];
			self::$shopProductSubCategory = $conOp['shop_productsubcategory'];
			self::$shopArticleSort = $conOp['shop_sortby'];
			self::$shopLinkTarget = $conOp['shop_linktarget'];
			self::$shopCheckoutIframe = intval($conOp['shop_checkoutiframe']);
			self::$shopDesignerShopId = intval($conOp['shop_designershop']);
			self::$shopDisplay = intval($conOp['shop_display']);
			self::$shopDesignsBackground = intval($conOp['shop_designsbackground']);
			self::$shopShowDescription = intval($conOp['shop_showdescription']);
			self::$shopShowExtendPrice = intval($conOp['shop_showextendprice']);
			self::$shopImgSize = (intval($conOp['shop_imagesize'])==0?190:intval($conOp['shop_imagesize']));
			self::$shopZoomImageBackgroundColor = (empty($conOp['shop_zoomimagebackground'])?'FFFFFF':str_replace("#", "", $conOp['shop_zoomimagebackground']));
			

			if (isset($_GET['productCategory'])) {
				$c = urldecode($_GET['productCategory']);
				self::$shopProductCategory = $c;
				self::$shopProductSubCategory = 'all';

				if (!empty($_GET['productSubCategory'])) {
					$c = urldecode($_GET['productSubCategory']);
					self::$shopProductSubCategory = $c;
				}
			}
			if (isset($_GET['articleSortBy'])) {
				$c = urldecode($_GET['articleSortBy']);
				self::$shopArticleSort = $c;
			}


			// At filtering articles don't use designs view
			if (self::$shopDisplay==1 && self::$shopProductCategory=='') {
			} else {
				self::$shopDisplay=0;
			}


			// check
			if(!empty(self::$shopId) && !empty(self::$shopApi) && !empty(self::$shopSecret)) {

				// use pagination value from wordpress
				if(empty($paged)) $paged = 1;

				$offset=($paged-1)*self::$shopLimit;


				// get article data
				$articleData=self::getArticleData();
				// get rid of types in array
				$typesData=$articleData['types'];
				unset($articleData['types']);

				// get designs data
				$designsData=self::getDesignsData();

				$intInBasket=self::getInBasketQuantity();

				// built second array with articles for sorting and filtering
				if (is_array($designsData)) {
					foreach ($designsData as $designId => $arrDesigns) {
						if (!empty($articleData[$designId])) {
							foreach ($articleData[$designId] as $articleId => $arrArticle) {
								$articleCleanData[$articleId] = $arrArticle;
							}
						}
					}
				}

				// filter
				if (is_array($articleCleanData)) {
					foreach ($articleCleanData as $id => $article) {
						if (!empty(self::$shopProductCategory)&&isset($typesData[self::$shopProductCategory][self::$shopProductSubCategory])) {
							if (!isset($typesData[self::$shopProductCategory][self::$shopProductSubCategory][$article['type']])) {
								unset($articleCleanData[$id]);
							}
						}
					}
				}


				//@krsort($designsData);
				@krsort($articleData);
				//@krsort($articleCleanData);

				
				@uasort($designsData,create_function('$a,$b',"return (\$a[place] < \$b[place])?-1:1;"));
				@uasort($articleCleanData,create_function('$a,$b',"return (\$a[place] < \$b[place])?-1:1;"));


				// sorting
				if (self::$shopDisplay==1) {
					if (!empty(self::$shopArticleSort) && is_array($designsData) && in_array(self::$shopArticleSort,self::$shopArticleSortOptions)) {
						if (self::$shopArticleSort=="recent") {
							krsort($designsData);
						} else if (self::$shopArticleSort=="price") {
							uasort($designsData,create_function('$a,$b',"return (\$a[pricenet] < \$b[pricenet])?-1:1;"));
						} else if (self::$shopArticleSort=="weight") {
							uasort($designsData,create_function('$a,$b',"return (\$a[weight] > \$b[weight])?-1:1;"));
						} else {
							uasort($designsData,create_function('$a,$b',"return strnatcmp(\$a[".self::$shopArticleSort."],\$b[".self::$shopArticleSort."]);"));
						}
					}
				} else {
					if (!empty(self::$shopArticleSort) && is_array($articleCleanData) && in_array(self::$shopArticleSort,self::$shopArticleSortOptions)) {
						if (self::$shopArticleSort=="recent") {
							krsort($articleCleanData);
						} else if (self::$shopArticleSort=="price") {
							uasort($articleCleanData,create_function('$a,$b',"return (\$a[pricenet] < \$b[pricenet])?-1:1;"));
						} else if (self::$shopArticleSort=="weight") {
							uasort($articleCleanData,create_function('$a,$b',"return (\$a[weight] > \$b[weight])?-1:1;"));
						} else {
							uasort($articleCleanData,create_function('$a,$b',"return strnatcmp(\$a[".self::$shopArticleSort."],\$b[".self::$shopArticleSort."]);"));
						}
					}
				}


				// pagination
				if (self::$shopDisplay==1) {
					if (!empty(self::$shopLimit) && is_array($designsData)) {
						$designsData = array_slice($designsData, $offset, self::$shopLimit, true);
					}
				} else {
					if (!empty(self::$shopLimit) && is_array($articleCleanData)) {
						$articleCleanData = array_slice($articleCleanData, $offset, self::$shopLimit, true);
					}
				}


				// Start output
				$output = '<div id="spreadshirt-items" class="spreadshirt-items clearfix">';

				// add spreadshirt-menu
				$output .= '<div id="spreadshirt-menu" class="spreadshirt-menu">';

				// add product categories
				$output .= '<select name="productCategory" id="productCategory">';
				$output .= '<option value="">'.__('Product category', $this->stringTextdomain).'</option>';
				if (isset($typesData)) {
					foreach ($typesData as $t => $v) {
						$output .= '<option value="'.urlencode($t).'"'.($t==self::$shopProductCategory?' selected':'').'>'.$t.'</option>';
					}
				}
				$output .= '</select> ';

				// simple sub categories
				// @TODO Javascript
				if (isset($_GET['productCategory'])) {
					$output .= '<select name="productSubCategory" id="productSubCategory">';
					$output .= '<option value="all"></option>';
					if (isset($typesData[self::$shopProductCategory])) {
						@ksort($typesData[self::$shopProductCategory]);
						unset($typesData[self::$shopProductCategory]['all']);
						foreach ($typesData[self::$shopProductCategory] as $t => $v) {
							$output .= '<option value="'.urlencode($t).'"'.($t==self::$shopProductSubCategory?' selected':'').'>'.$t.'</option>';
						}
					}
					$output .= '</select> ';
				}

				// add sorting
				$output .= '<select name="articleSortBy" id="articleSortBy">';
				$output .= '<option value="">'.__('Sort by', $this->stringTextdomain).'</option>';
				$output .= '<option value="name"'.('name'==self::$shopArticleSort?' selected':'').'>'.__('name', $this->stringTextdomain).'</option>';
				$output .= '<option value="price"'.('price'==self::$shopArticleSort?' selected':'').'>'.__('price', $this->stringTextdomain).'</option>';
				$output .= '<option value="recent"'.('recent'==self::$shopArticleSort?' selected':'').'>'.__('recent', $this->stringTextdomain).'</option>';
				$output .= '<option value="weight"'.('weight'==self::$shopArticleSort?' selected':'').'>'.__('weight', $this->stringTextdomain).'</option>';
				$output .= '</select>';

				if (isset($_SESSION['checkoutUrl']) && $intInBasket>0) {
					$output .= ' <div id="checkout"><span>'.$intInBasket."</span> <a href=".$_SESSION['checkoutUrl']." target=\"".self::$shopLinkTarget."\">".__('Basket', $this->stringTextdomain)."</a></div>";
				} else {
					$output .= ' <div id="checkout"><span>'.$intInBasket."</span> <a title=\"".__('Basket is empty', $this->stringTextdomain)."\">".__('Basket', $this->stringTextdomain)."</a></div>";
				}

				$output .= '</div>';

				// display
				if (count($articleData) == 0 || $articleData==false) {

					$output .= '<br>No articles in Shop';

				} else {

					$output .= '<div id="spreadshirt-list">';

					// Designs view
					if (self::$shopDisplay==1) {
						foreach ($designsData as $designId => $arrDesigns) {
							$bgc = false;
							$addStyle = '';
								
							// Display just Designs with products
							if (!empty($articleData[$designId])) {

								// check if designs background is enabled
								if (self::$shopDesignsBackground==1) {
									// fetch first article background color
									@reset($articleData[$designId]);
									$bgcV=$articleData[$designId][key($articleData[$designId])]['default_bgc'];
									$bgcV=str_replace("#", "", $bgcV);
									// calc to hex
									$bgc=$this->hex2rgb($bgcV);
									$addStyle="style=\"background-color:rgba(".$bgc[0].",".$bgc[1].",".$bgc[2].",0.4);\"";
								}

								$output .= "<div class=\"spreadshirt-designs\">";
								$output .= $this->displayDesigns($designId,$arrDesigns,$articleData[$designId],$bgc);
								$output .= "<div id=\"designContainer_".$designId."\" class=\"design-container clearfix\" ".$addStyle.">";
									
								if (!empty($articleData[$designId])) {
									foreach ($articleData[$designId] as $articleId => $arrArticle) {
										$output .= $this->displayArticles($articleId,$arrArticle,self::$shopZoomImageBackgroundColor); // ,$bgcV
									}
								}

								$output .= "</div>";
								$output .= "</div>";
							}
						}
					} else {
						// Article view
						if (!empty($articleCleanData)) {
							foreach ($articleCleanData as $articleId => $arrArticle) {
								$output .= $this->displayArticles($articleId,$arrArticle,self::$shopZoomImageBackgroundColor);
							}
						}
					}


					$output .= "
							<div id=\"pagination\"><a href=\"".get_pagenum_link($paged + 1)."\">".__('next', $this->stringTextdomain)."</a></div>
									<!-- <div id=\"copyright\">Copyright (c) Thimo Grauerholz - <a href=\"http://www.pr3ss-play.de\">pr3ss-play - Dein Shirt-Shop für geile Party T-shirts!</a></div> -->
									</div>";
				}


				$output .= '</div>';

				return $output;

			}
		}


		/**
		 * Function getArticleData
		 *
		 * Retrieves article data and save into cache
		 *
		 * @return array Article data
		 */
		private static function getArticleData() {
			$arrTypes=array();

			// retrieve id of post to save as different content, if shortcode is available in more than one post (more than one shop in the wordpress website)
			$articleData = get_transient('spreadplugin2-article-cache-'.get_the_ID());

			if($articleData === false) {

				$apiUrlBase = 'http://api.spreadshirt.'.self::$apiUrl.'/api/v1/shops/' . self::$shopId;
				$apiUrlBase .= (!empty(self::$shopCategoryId)?'/articleCategories/'.self::$shopCategoryId:'');
				$apiUrlBase .= '/articles?'.(!empty(self::$shopLocale)?'locale=' . self::$shopLocale . '&':'').'fullData=true';

				// call first to get count of articles
				$apiUrl = $apiUrlBase . '&limit='.rand(2,999); // randomize to avoid spreadshirt caching issues

				$stringXmlShop = wp_remote_get($apiUrl);
				if (count($stringXmlShop->errors)>0) die('Error getting articles. Please check Shop-ID, API and secret.');
				if ($stringXmlShop['body'][0]!='<') die($stringXmlShop['body']);
				$stringXmlShop = wp_remote_retrieve_body($stringXmlShop);
				$objArticles = new SimpleXmlElement($stringXmlShop);
				if (!is_object($objArticles)) die('Articles not loaded');

				// re-call to read articles with count
				// read max 1000 articles because of spreadshirt max. limit
				$apiUrl = $apiUrlBase . '&limit='.($objArticles['count']<=1?2:($objArticles['count']<1000?$objArticles['count']:1000));

				$stringXmlShop = wp_remote_get($apiUrl);
				if (count($stringXmlShop->errors)>0) die('Error getting articles. Please check your Shop-ID.');
				if ($stringXmlShop['body'][0]!='<') die($stringXmlShop['body']);
				$stringXmlShop = wp_remote_retrieve_body($stringXmlShop);
				$objArticles = new SimpleXmlElement($stringXmlShop);
				if (!is_object($objArticles)) die('Articles not loaded');


				if ($objArticles['count']>0) {

					// ProductTypeDepartments
					$stringTypeApiUrl = 'http://api.spreadshirt.'.self::$apiUrl.'/api/v1/shops/' . self::$shopId.'/productTypeDepartments?'.(!empty(self::$shopLocale)?'locale=' . self::$shopLocale . '&':'').'fullData=true';
					$stringTypeXml = wp_remote_get($stringTypeApiUrl);
					$stringTypeXml = wp_remote_retrieve_body($stringTypeXml);
					$objTypes = new SimpleXmlElement($stringTypeXml);

					if (is_object($objTypes)) {
						foreach ($objTypes->productTypeDepartment as $row) {
							foreach ($row->categories->category as $subrow) {
								foreach ($subrow->productTypes as $subrow2) {
									foreach ($subrow2->productType as $subrow3) {
										$arrTypes[(string)$row->name][(string)$subrow->name][(int)$subrow3['id']] = 1;
										$arrTypes[(string)$row->name]['all'][(int)$subrow3['id']] = 1;
									}
								}
							}
						}
					}

					$articleData['types'] = $arrTypes;


					// read articles
					$i=0;
					foreach ($objArticles->article as $article) {

						$stringXmlArticle = wp_remote_retrieve_body(wp_remote_get($article->product->productType->attributes('xlink', true).'?'.(!empty(self::$shopLocale)?'locale=' . self::$shopLocale:'')));
						if(substr($stringXmlArticle, 0, 5) !== "<?xml") continue;
						$objArticleData = new SimpleXmlElement($stringXmlArticle);
						$stringXmlCurreny = wp_remote_retrieve_body(wp_remote_get($article->price->currency->attributes('http://www.w3.org/1999/xlink')));
						if(substr($stringXmlArticle, 0, 5) !== "<?xml") continue;
						$objCurrencyData = new SimpleXmlElement($stringXmlCurreny);

						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['name']=(string)$article->name;
						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['description']=(string)$article->description;
						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['appearance']=(int)$article->product->appearance['id'];
						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['view']=(int)$article->product->defaultValues->defaultView['id'];
						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['type']=(int)$article->product->productType['id'];
						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['productId']=(int)$article->product['id'];
						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['pricenet']=(float)$article->price->vatExcluded;
						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['pricebrut']=(float)$article->price->vatIncluded;
						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['currencycode']=(string)$objCurrencyData->isoCode;
						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['productname']=(string)$objArticleData->name;
						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['productdescription']=(string)$objArticleData->description;
						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['weight']=(float)$article['weight'];
						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['id']=(int)$article['id'];
						$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['place']=$i;

						foreach($objArticleData->sizes->size as $val) {
							$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['sizes'][(int)$val['id']]=(string)$val->name;
						}

						foreach($objArticleData->appearances->appearance as $appearance) {
							if ((int)$article->product->appearance['id'] == (int)$appearance['id']) {
								$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['default_bgc'] = (string)$appearance->colors->color;
							}
								
							if ($article->product->restrictions->freeColorSelection == 'true' || (int)$article->product->appearance['id'] == (int)$appearance['id']) {
								$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['appearances'][(int)$appearance['id']]=(string)$appearance->resources->resource->attributes('xlink', true);
							}
						}

						foreach($objArticleData->views->view as $view) {
							$articleData[(int)$article->product->defaultValues->defaultDesign['id']][(int)$article['id']]['views'][(int)$view['id']]=(string)$article->resources->resource->attributes('xlink', true);
						}
						
						$i++;
					}

					set_transient('spreadplugin2-article-cache-'.get_the_ID(), $articleData, self::$shopCache*3600);
				}
			}

			return $articleData;
		}





		/**
		 * Function getDesignsData
		 *
		 * Retrieves design data and save into cache
		 *
		 * @return array designs data
		 */
		private static function getDesignsData() {
			$arrTypes=array();

			// retrieve id of post to save as different content, if shortcode is available in more than one post (more than one shop in the wordpress website)
			$articleData = get_transient('spreadplugin2-designs-cache-'.get_the_ID());

			if($articleData === false) {

				$apiUrlBase = 'http://api.spreadshirt.'.self::$apiUrl.'/api/v1/shops/' . self::$shopId;
				//$apiUrlBase .= (!empty(self::$shopCategoryId)?'/articleCategories/'.self::$shopCategoryId:'');
				$apiUrlBase .= '/designs?'.(!empty(self::$shopLocale)?'locale=' . self::$shopLocale . '&':'').'fullData=true';

				// call first to get count of articles
				$apiUrl = $apiUrlBase . '&limit='.rand(2,999); // randomize to avoid spreadshirt caching issues

				$stringXmlShop = wp_remote_get($apiUrl);
				if (count($stringXmlShop->errors)>0) die('Error getting articles. Please check Shop-ID, API and secret.');
				if ($stringXmlShop['body'][0]!='<') die($stringXmlShop['body']);
				$stringXmlShop = wp_remote_retrieve_body($stringXmlShop);
				$objArticles = new SimpleXmlElement($stringXmlShop);
				if (!is_object($objArticles)) die('Articles not loaded');

				// re-call to read articles with count
				// read max 1000 articles because of spreadshirt max. limit
				$apiUrl = $apiUrlBase . '&limit='.($objArticles['count']<=1?2:($objArticles['count']<1000?$objArticles['count']:1000));

				$stringXmlShop = wp_remote_get($apiUrl);
				if (count($stringXmlShop->errors)>0) die('Error getting articles. Please check your Shop-ID.');
				if ($stringXmlShop['body'][0]!='<') die($stringXmlShop['body']);
				$stringXmlShop = wp_remote_retrieve_body($stringXmlShop);
				$objArticles = new SimpleXmlElement($stringXmlShop);
				if (!is_object($objArticles)) die('Articles not loaded');


				if ($objArticles['count']>0) {

					// read articles
					$i=0;
					foreach ($objArticles->design as $article) {

						$articleData[(int)$article['id']]['name']=(string)$article->name;
						$articleData[(int)$article['id']]['description']=(string)$article->description;
						$articleData[(int)$article['id']]['appearance']=(int)$article->product->appearance['id'];
						$articleData[(int)$article['id']]['view']=(int)$article->product->defaultValues->defaultView['id'];
						$articleData[(int)$article['id']]['type']=(int)$article->product->productType['id'];
						$articleData[(int)$article['id']]['productId']=(int)$article->product['id'];
						$articleData[(int)$article['id']]['pricenet']=(float)$article->price->vatExcluded;
						$articleData[(int)$article['id']]['pricebrut']=(float)$article->price->vatIncluded;
						$articleData[(int)$article['id']]['currencycode']=(string)$objCurrencyData->isoCode;
						$articleData[(int)$article['id']]['resource0']=(string)$article->resources->resource[0]->attributes('xlink', true);
						$articleData[(int)$article['id']]['resource2']=(string)$article->resources->resource[1]->attributes('xlink', true);
						$articleData[(int)$article['id']]['productdescription']=(string)$objArticleData->description;
						$articleData[(int)$article['id']]['weight']=(float)$article['weight'];
						$articleData[(int)$article['id']]['place']=$i;
						
						$i++;
					}

					set_transient('spreadplugin2-designs-cache-'.get_the_ID(), $articleData, self::$shopCache*3600);
				}
			}

			return $articleData;
		}



		/**
		 * Function displayArticles
		 *
		 * Displays the articles
		 *
		 * @return html
		 */
		private function displayArticles($id,$article,$backgroundColor='') {
				
			$output = '<div class="spreadshirt-article clearfix" id="article_'.$id.'" style="width:'.(self::$shopImgSize+7).'px">';
			$output .= '<a name="'.$id.'"></a>';
			$output .= '<h3>'.htmlspecialchars($article['name'],ENT_QUOTES).'</h3>';
			$output .= '<form method="post" id="form_'.$id.'">';
			$output .= '<div class="image-wrapper">';
			//$output .= (self::$shopLinkEnabled==1?'<a href="//'.self::$shopId.'.spreadshirt.'.self::$apiUrl.'/-A'.$id.'" target="'.self::$shopLinkTarget.'">':'');
			$output .= '<img src="http://image.spreadshirt.'.self::$apiUrl.'/image-server/v1/products/'.$article['productId'].'/views/1,width='.self::$shopImgSize.',height='.self::$shopImgSize.'" class="preview" alt="' . htmlspecialchars($article['name'],ENT_QUOTES) . '" id="previewimg_'.$id.'" data-zoom-image="http://image.spreadshirt.'.self::$apiUrl.'/image-server/v1/products/'.$article['productId'].'/views/1,width=800,height=800'.(!empty($backgroundColor)?',backgroundColor='.$backgroundColor:'').'" />';
			//$output .= (self::$shopLinkEnabled==1?'</a>':'');
			$output .= '</div>';

			// add a select with available sizes
			if (isset($article['sizes'])&&is_array($article['sizes'])) {
				$output .= '<select id="size-select" name="size">';

				foreach($article['sizes'] as $k => $v) {
					$output .= '<option value="'.$k.'">'.$v.'</option>';
				}

				$output .= '</select>';
			}

			if (self::$shopDesignerShopId>0) {
				$output .= ' <a href="//'.self::$shopDesignerShopId.'.spreadshirt.'.self::$apiUrl.'/-D1/customize/product/'.$article['productId'].'?noCache=true" target="_blank" id="editArticle">'.__('Edit article', $this->stringTextdomain).'</a>';
			}
				
			$output .= '<div class="separator"></div>';

			// add a list with availabel product colors
			if (isset($article['appearances'])&&is_array($article['appearances'])) {
				$output .= '<ul class="colors" name="color">';

				foreach($article['appearances'] as $k=>$v) {
					$output .= '<li value="'.$k.'"><img src="'. $this->cleanURL($v) .'" alt="" /></li>';
				}

				$output .= '</ul>';
			}

				
			// add a list with available product views
			if (isset($article['views'])&&is_array($article['views'])) {
				$output .= '<ul class="views" name="views">';

				foreach($article['views'] as $k=>$v) {
					$output .= '<li value="'.$k.'"><img src="'. $this->cleanURL($v)  .',viewId='.$k.',width=42,height=42" class="previewview" alt="" id="viewimg_'.$id.'" /></li>';
				}

				$output .= '</ul>';
			}

			// Short product description
			$output .= '<div class="separator"></div>';
			$output .= '<div class="product-name">';
			$output .= htmlspecialchars($article['productname'],ENT_QUOTES);
			$output .= '</div>';

			// Show description link if not empty
			if (!empty($article['description'])) {
				$output .= '<div class="separator"></div>';
				
				if (self::$shopShowDescription==0) {
					$output .= '<div class="description-wrapper"><div class="header"><a>'.__('Show description', $this->stringTextdomain).'</a></div><div class="description">'.htmlspecialchars($article['description'],ENT_QUOTES).'</div></div>';
				} else {
					$output .= '<div class="description-wrapper">'.htmlspecialchars($article['description'],ENT_QUOTES).'</div>';
				}
			}
				
			$output .= '<input type="hidden" value="'. $article['appearance'] .'" id="appearance" name="appearance" />';
			$output .= '<input type="hidden" value="'. $article['view'] .'" id="view" name="view" />';
			$output .= '<input type="hidden" value="'. $id .'" id="article" name="article" />';
				
			$output .= '<div class="separator"></div>';
			$output .= '<div class="price-wrapper">';
			if (self::$shopShowExtendPrice==1) {
				$output .= '<span id="price-without-tax">'.__('Price (without tax):', $this->stringTextdomain)." ".(empty(self::$shopLocale) || self::$shopLocale=='en_US' || self::$shopLocale=='en_GB'?number_format($article['pricenet'],2,'.',''):number_format($article['pricenet'],2,',','.'))." ".$article['currencycode']."<br /></span>";
				$output .= '<span id="price-with-tax">'.__('Price (with tax):', $this->stringTextdomain)." ".(empty(self::$shopLocale) || self::$shopLocale=='en_US' || self::$shopLocale=='en_GB'?number_format($article['pricebrut'],2,'.',''):number_format($article['pricebrut'],2,',','.'))." ".$article['currencycode']."</span>";
			} else {
				$output .= '<span id="price">'.__('Price:', $this->stringTextdomain)." ".(empty(self::$shopLocale) || self::$shopLocale=='en_US' || self::$shopLocale=='en_GB'?number_format($article['pricebrut'],2,'.',''):number_format($article['pricebrut'],2,',','.'))." ".$article['currencycode']."</span>";
			}
			$output .= '</div>';
				
			// order buttons
			$output .= '<input type="text" value="1" id="quantity" name="quantity" maxlength="4" />';
			$output .= '<input type="submit" name="submit" value="'.__('Add to basket', $this->stringTextdomain).'" /><br>';

			// Social buttons
			if (self::$shopSocialEnabled==true) {
				$output .= '
						<ul class="soc-icons">
						<li><a target="_blank" data-color="#5481de" class="fb" href="//www.facebook.com/sharer.php?u='.urlencode(get_page_link().'#'.$id).'&t='.rawurlencode(get_the_title()).'" title="Facebook"></a></li>
						<li><a target="_blank" data-color="#06ad18" class="goog" href="//plus.google.com/share?url='.urlencode(get_page_link().'#'.$id).'" title="Google"></a></li>
						<li><a target="_blank" data-color="#2cbbea" class="twt" href="//twitter.com/home?status='.rawurlencode(get_the_title()).' - '.urlencode(get_page_link().'#'.$id).'" title="Twitter"></a></li>
						<li><a target="_blank" data-color="#e84f61" class="pin" href="//pinterest.com/pin/create/button/?url='.get_page_link().'&media=' . $article['resource0'] . ',width='.self::$shopImgSize.',height='.self::$shopImgSize.'&description='.(!empty($article['description'])?htmlspecialchars($article['description'],ENT_QUOTES):'Product').'" title="Pinterest"></a></li>
						</ul>
						';

				/*
					<li><a target="_blank" data-color="#459ee9" class="in" href="#" title="LinkedIn"></a></li>
				<li><a target="_blank" data-color="#ee679b" class="drb" href="#" title="Dribbble"></a></li>
				<li><a target="_blank" data-color="#4887c2" class="tumb" href="#" title="Tumblr"></a></li>
				<li><a target="_blank" data-color="#f23a94" class="flick" href="#" title="Flickr"></a></li>
				<li><a target="_blank" data-color="#74c3dd" class="vim" href="#" title="Vimeo"></a></li>
				<li><a target="_blank" data-color="#4a79ff" class="delic" href="#" title="Delicious"></a></li>
				<li><a target="_blank" data-color="#6ea863" class="forr" href="#" title="Forrst"></a></li>
				<li><a target="_blank" data-color="#f6a502" class="hi5" href="#" title="Hi5"></a></li>
				<li><a target="_blank" data-color="#e3332a" class="last" href="#" title="Last.fm"></a></li>
				<li><a target="_blank" data-color="#3c6ccc" class="space" href="#" title="Myspace"></a></li>
				<li><a target="_blank" data-color="#229150" class="newsv" href="#" title="Newsvine"></a></li>
				<li><a href="#" class="pica" title="Picasa" data-color="#b163c8" target="_blank"></a></li>
				<li><a href="#" class="tech" title="Technorati" data-color="#3ac13a" target="_blank"></a></li>
				<li><a href="#" class="rss" title="RSS" data-color="#f18d3c" target="_blank"></a></li>
				<li><a href="#" class="rdio" title="Rdio" data-color="#2c7ec7" target="_blank"></a></li>
				<li><a href="#" class="share" title="ShareThis" data-color="#359949" target="_blank"></a></li>
				<li><a href="#" class="skyp" title="Skype" data-color="#00adf1" target="_blank"></a></li>
				<li><a href="#" class="slid" title="SlideShare" data-color="#ef8122" target="_blank"></a></li>
				<li><a href="#" class="squid" title="Squidoo" data-color="#f87f27" target="_blank"></a></li>
				<li><a href="#" class="stum" title="StumbleUpon" data-color="#f05c38" target="_blank"></a></li>
				<li><a href="#" class="what" title="WhatsApp" data-color="#3ebe2b" target="_blank"></a></li>
				<li><a href="#" class="wp" title="Wordpress" data-color="#3078a9" target="_blank"></a></li>
				<li><a href="#" class="ytb" title="Youtube" data-color="#df3434" target="_blank"></a></li>
				<li><a href="#" class="digg" title="Digg" data-color="#326ba0" target="_blank"></a></li>
				<li><a href="#" class="beh" title="Behance" data-color="#2d9ad2" target="_blank"></a></li>
				<li><a href="#" class="yah" title="Yahoo" data-color="#883890" target="_blank"></a></li>
				<li><a href="#" class="blogg" title="Blogger" data-color="#f67928" target="_blank"></a></li>
				<li><a href="#" class="hype" title="Hype Machine" data-color="#f13d3d" target="_blank"></a></li>
				<li><a href="#" class="groove" title="Grooveshark" data-color="#498eba" target="_blank"></a></li>
				<li><a href="#" class="sound" title="SoundCloud" data-color="#f0762c" target="_blank"></a></li>
				<li><a href="#" class="insta" title="Instagram" data-color="#c2784e" target="_blank"></a></li>
				<li><a href="#" class="vk" title="Vkontakte" data-color="#5f84ab" target="_blank"></a></li>
				*/
			}
				
			$output .= '
						
					</form>
					</div>';
				
				
			return $output;

		}


		/**
		 * Function displayDesigns
		 *
		 * Displays the designs
		 *
		 * @return html
		 */
		private function displayDesigns($id,$designData,$articleData,$bgc=false) {
				
			$addStyle = '';
			if ($bgc) $addStyle='style="background-color:rgba('.$bgc[0].','.$bgc[1].','.$bgc[2].',0.4);"';

			$output = '<div class="spreadshirt-design clearfix" id="design_'.$id.'" style="width:187px">';
			$output .= '<a name="'.$id.'"></a>';
			$output .= '<h3>'.htmlspecialchars($designData['name'],ENT_QUOTES).'</h3>';
			$output .= '<div class="image-wrapper" '.$addStyle.'>';
			$output .= '<img src="' . $this->cleanURL($designData['resource2']) . ',width='.self::$shopImgSize.',height='.self::$shopImgSize.'" alt="' . htmlspecialchars($designData['name'],ENT_QUOTES) . '" id="compositedesignimg_'.$id.'" />'; // style="display:none;" // title="'.htmlspecialchars($designData['productdescription'],ENT_QUOTES).'"
			$output .= '<span class="img-caption">'.__('Click to view the articles', $this->stringTextdomain).'</em></span>';
			$output .= '</div>';

			// Show description link if not empty
			if (!empty($designData['description']) && $designData['description']!='null') {
				$output .= '<div class="separator"></div>';
				$output .= '<div class="description-wrapper">
				<div class="header"><a>'.__('Show description', $this->stringTextdomain).'</a></div>
				<div class="description">'.htmlspecialchars($designData['description'],ENT_QUOTES).'</div>
				</div>';
			}
				
			$output .= '
			</div>';

			return $output;

		}




		/**
		 * Function Add basket item
		 *
		 * @param $basketUrl
		 * @param $namespaces
		 * @param array $data
		 *
		 */
		private static function addBasketItem($basketUrl, $namespaces, $data) {

			$basketItemsUrl = $basketUrl . "/items";

			$basketItem = new SimpleXmlElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
					<basketItem xmlns:xlink="http://www.w3.org/1999/xlink" xmlns="http://api.spreadshirt.net">
					<quantity>' . $data['quantity'] . '</quantity>
					<element id="' . $data['articleId'] . '" type="sprd:article" xlink:href="http://api.spreadshirt.'.self::$apiUrl.'/api/v1/shops/' . $data['shopId'] . '/articles/' . $data['articleId'] . '">
					<properties>
					<property key="appearance">' . $data['appearance'] . '</property>
					<property key="size">' . $data['size'] . '</property>
					</properties>
					</element>
					<links>
					<link type="edit" xlink:href="http://' . $data['shopId'] .'.spreadshirt.' .self::$apiUrl.'/-A' . $data['articleId'] . '"/>
					<link type="continueShopping" xlink:href="http://' . $data['shopId'].'.spreadshirt.'.self::$apiUrl.'"/>
					</links>
					</basketItem>');

			$header = array();
			$header[] = self::createAuthHeader("POST", $basketItemsUrl);
			$header[] = "Content-Type: application/xml";
			$result = self::oldHttpRequest($basketItemsUrl, $header, 'POST', $basketItem->asXML());

			if ($result) {
			} else {
				die('ERROR: Item not added.');
			}

		}


		/**
		 * Function Create basket
		 *
		 * @param $platform
		 * @param $shop
		 * @param $namespaces
		 *
		 * @return string $basketUrl
		 *
		 */
		private static function createBasket($platform, $shop, $namespaces) {

			$basket = new SimpleXmlElement('<basket xmlns:xlink="http://www.w3.org/1999/xlink" xmlns="http://api.spreadshirt.net">
					<shop id="' . $shop['id'] . '"/>
					</basket>');

			$attributes = $shop->baskets->attributes($namespaces['xlink']);
			$basketsUrl = $attributes->href;
			$header = array();
			$header[] = self::createAuthHeader("POST", $basketsUrl);
			$header[] = "Content-Type: application/xml";
			$result = self::oldHttpRequest($basketsUrl, $header, 'POST', $basket->asXML());

			if ($result) {
				$basketUrl = self::parseHttpHeaders($result, "Location");
			} else {
				die('ERROR: Basket not ready yet.');
			}

			return $basketUrl;

		}


		/**
		 * Function Checkout
		 *
		 * @param $basketUrl
		 * @param $namespaces
		 *
		 * @return string $checkoutUrl
		 *
		 */
		private static function checkout($basketUrl, $namespaces) {
			$checkoutUrl='';

			$basketCheckoutUrl = $basketUrl . "/checkout";
			$header = array();
			$header[] = self::createAuthHeader("GET", $basketCheckoutUrl);
			$header[] = "Content-Type: application/xml";
			$result = self::oldHttpRequest($basketCheckoutUrl, $header, 'GET');

			if ($result[0]=='<') {
				$checkoutRef = new SimpleXMLElement($result);
				$refAttributes = $checkoutRef->attributes($namespaces['xlink']);
				$checkoutUrl = (string)$refAttributes->href;
			} else {
				die('ERROR: Can\'t get checkout url.');
			}

			return $checkoutUrl;
		}


		/**
		 * Function createAuthHeader
		 *
		 * Creates authentification header
		 *
		 * @param string $method [POST,GET]
		 * @param string $url
		 *
		 * @return string
		 *
		 */
		private static function createAuthHeader($method, $url) {

			$time = microtime();

			$data = "$method $url $time";
			$sig = sha1("$data ".self::$shopSecret);

			return "Authorization: SprdAuth apiKey=\"".self::$shopApi."\", data=\"$data\", sig=\"$sig\"";

		}


		/**
		 * Function parseHttpHeaders
		 *
		 * @param string $header
		 * @param string $headername needle
		 * @return string $retval value
		 *
		 */
		private static function parseHttpHeaders($header, $headername) {

			$retVal = array();
			$fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));

			foreach($fields as $field) {
				if (preg_match('/(' . $headername . '): (.+)/m', $field, $match)) {
					return $match[2];
				}
			}

			return $retVal;
		}


		/**
		 * Function getBasket
		 *
		 * retrieves the basket
		 *
		 * @param string $basketUrl
		 * @return object $basket
		 *
		 */
		private static function getBasket($basketUrl) {

			$header = array();
			$basket = "";

			if (!empty($basketUrl)) {
				$header[] = self::createAuthHeader("GET", $basketUrl);
				$header[] = "Content-Type: application/xml";
				$result = self::oldHttpRequest($basketUrl, $header, 'GET');
				if ($result[0]=='<') {
					$basket = new SimpleXMLElement($result);
				}
			}

			return $basket;

		}


		/**
		 * Function getInBasketQuantity
		 *
		 * retrieves quantity of articles in basket
		 *
		 * @return int $intInBasket Quantity of articles
		 *
		 */
		private static function getInBasketQuantity() {
			if (isset($_SESSION['basketUrl'])) {
					
				$basketItems=self::getBasket($_SESSION['basketUrl']);

				if(!empty($basketItems)) {
					foreach($basketItems->basketItems->basketItem as $item) {
						$intInBasket += $item->quantity;
					}
				}
			}
			return $intInBasket;
		}


		/**
		 * Function oldHttpRequest
		 *
		 * creates the curl requests, until I get a fix for the wordpress request problems
		 *
		 * @param $url
		 * @param $header
		 * @param $method
		 * @param $data
		 * @param $len
		 *
		 * @return string|bool
		 *
		 */
		private static function oldHttpRequest($url, $header = null, $method = 'GET', $data = null, $len = null) {

			switch ($method) {

				case 'GET':

					$ch = curl_init($url);
					curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_HEADER, false);
					curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

					break;

				case 'POST':

					$ch = curl_init($url);
					curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_HEADER, true);
					curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
					curl_setopt($ch, CURLOPT_POST, true); //not createBasket but addBasketItem
					curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

					break;

			}

			$result = curl_exec($ch);
			$info = curl_getinfo($ch);
			$status = isset($info['http_code'])?$info['http_code']:null;
			@curl_close($ch);

			if (in_array($status,array(200,201,204,403,406))) {
				return $result;
			}

			return false;
		}


		/**
		 * Function loadScripts
		 *
		 */
		public function loadScripts() {
			echo "
					<script>
					/**
					* Spreadplugin vars
					*/
						
					var textHideDesc = '".__('Hide description', $this->stringTextdomain)."';
					var textShowDesc = '".__('Show description', $this->stringTextdomain)."';
					var loadingImage = '".plugins_url('/img/loading.gif', __FILE__)."';
					var loadingMessage = '".__('Loading new articles...', $this->stringTextdomain)."';
					var loadingFinishedMessage = '".__('You have reached the end', $this->stringTextdomain)."';
					var pageLink = '".get_page_link()."';
					var pageCheckoutUseIframe = ".self::$shopCheckoutIframe.";
					var textButtonAdd = '".__('Add to basket', $this->stringTextdomain)."';
					var textButtonAdded = '".__('Adding...', $this->stringTextdomain)."';
					var ajaxLocation = '".admin_url( 'admin-ajax.php' )."?pageid=".get_the_ID()."&nonce=".wp_create_nonce('spreadplugin')."';
					var display = ".self::$shopDisplay.";
					var imageSize = ".self::$shopImgSize.";
					</script>";

			echo "
					<script src='".plugins_url('/js/spreadplugin.js', __FILE__)."'></script>
							";
		}


		public function startSession() {
			if(!session_id()) {
				@session_start();
			}
		}

		public function endSession() {
			@session_destroy();
		}

		// prepare for https
		private function cleanURL($url) {
			return str_replace('http:','',$url);
		}


		/**
		 * Function doAjax
		 *
		 * does all the ajax
		 *
		 * @return string json
		 *
		 */
		public function doAjax() {

			if (!wp_verify_nonce($_GET['nonce'], 'spreadplugin')) die('Security check');


			/**
			 * re-parse the shortcode to get the authentication details
			 *
			 * @TODO find a different way
			 *
			*/
			$pageData = get_page(intval($_GET['pageid']));
			$pageContent = $pageData->post_content;

			// get admin options (default option set on admin page)
			$conOp = $this->getAdminOptions();
				
			// shortcode overwrites admin options (default option set on admin page) if available
			$arrSc = shortcode_parse_atts(str_replace("[spreadplugin",'',str_replace("]","",$pageContent)));
				
			// replace options by shortcode if set
			if (!empty($arrSc)) {
				foreach ($arrSc as $key => $option) {
					if ($option != '') {
						$conOp[$key] = $option;
					}
				}
			}

			self::$shopId = intval($conOp['shop_id']);
			self::$shopApi = $conOp['shop_api'];
			self::$shopSecret = $conOp['shop_secret'];
			self::$shopLimit = intval($conOp['shop_limit']);
			self::$shopLocale = (($conOp['shop_locale']=='' || $conOp['shop_locale']=='de_DE') && $conOp['shop_source']=='com'?'us_US':$conOp['shop_locale']); // Workaround for older versions of this plugin
			self::$apiUrl = (empty($conOp['shop_source'])?'net':$conOp['shop_source']);


			// create an new basket if not exist
			if (!isset($_SESSION['basketUrl'])) {

				// gets basket
				$apiUrl = 'http://api.spreadshirt.'.self::$apiUrl.'/api/v1/shops/' . self::$shopId;
				$stringXmlShop = wp_remote_get($apiUrl);
				if (count($stringXmlShop->errors)>0) die('Error getting basket.');
				if ($stringXmlShop['body'][0]!='<') die($stringXmlShop['body']);
				$stringXmlShop = wp_remote_retrieve_body($stringXmlShop);
				$objShop = new SimpleXmlElement($stringXmlShop);
				if (!is_object($objShop)) die('Basket not loaded');

				// create the basket
				$namespaces = $objShop->getNamespaces(true);
				$basketUrl = self::createBasket('net', $objShop, $namespaces);
					
				if (empty($namespaces)) die('Namespaces empty');
				if (empty($basketUrl)) die('Basket url empty');
					
				// get the checkout url
				$checkoutUrl = self::checkout($basketUrl, $namespaces);

				// saving to session
				$_SESSION['basketUrl'] = $basketUrl;
				$_SESSION['namespaces'] = $namespaces;
				$_SESSION['checkoutUrl'] = $checkoutUrl;

			}


			// add an article to the basket
			if (isset($_POST['size']) && isset($_POST['appearance']) && isset($_POST['quantity'])) {

				// article data to be sent to the basket resource
				$data = array(
						'articleId' => intval($_POST['article']),
						'size' => intval($_POST['size']),
						'appearance' => intval($_POST['appearance']),
						'quantity' => intval($_POST['quantity']),
						'shopId' => self::$shopId
				);

				// add to basket
				self::addBasketItem($_SESSION['basketUrl'] , $_SESSION['namespaces'] , $data);

				$intInBasket=self::getInBasketQuantity();

				echo json_encode(array("c" => array("u" => $_SESSION['checkoutUrl'],"q" => $intInBasket)));
				die();
			}
		}





		/**
		 * Admin
		 */
		public function addPluginPage(){
			// Create menu tab
			add_options_page('Set Spreadplugin options', 'Spreadplugin Options', 'manage_options', 'splg_options', array($this, 'pageOptions'));
		}

		// call page options
		public function pageOptions(){
			if (!current_user_can('manage_options')){
				wp_die( __('You do not have sufficient permissions to access this page.') );
			}

			// display options page
			include(plugin_dir_path(__FILE__).'/options.php');
		}

		// Ajax delete the transient
		public function doRegenerateCache() {
			$this->setRegenerateCacheQuery();
			die();
		}
		// delete the transient
		public function setRegenerateCacheQuery() {
			global $wpdb;
			$wpdb->query("DELETE FROM `".$wpdb->options."` WHERE `option_name` LIKE '_transient_%spreadplugin%cache%'");
		}


		/**
		 * Add Settings link to plugin
		 */
		public function addPluginSettingsLink($links, $file) {
			static $this_plugin;
			if (!$this_plugin) $this_plugin = plugin_basename(__FILE__);

			if ($file == $this_plugin){
				$settings_link = '<a href="options-general.php?page=splg_options">'.__("Settings", $this->stringTextdomain) .'</a>';
				array_unshift($links, $settings_link);
			}
				
			return $links;
		}


		// Convert hex to rgb values
		public function hex2rgb($hex) {
			if(strlen($hex) == 3) {
				$r = hexdec(substr($hex,0,1).substr($hex,0,1));
				$g = hexdec(substr($hex,1,1).substr($hex,1,1));
				$b = hexdec(substr($hex,2,1).substr($hex,2,1));
			} else {
				$r = hexdec(substr($hex,0,2));
				$g = hexdec(substr($hex,2,2));
				$b = hexdec(substr($hex,4,2));
			}
			$rgb = array($r, $g, $b);
			return $rgb; // returns an array with the rgb values
		}


		// read admin options
		public function getAdminOptions() {
			$scOptions = $this->defaultOptions;
			$splgOptions = get_option('splg_options');
			if (!empty($splgOptions)) {
				foreach($splgOptions as $key => $option) {
					$scOptions[$key] = $option;
				}
			}
				
			return $scOptions;
		}



	} // END class WP_Spreadplugin

	new WP_Spreadplugin();
}



?>