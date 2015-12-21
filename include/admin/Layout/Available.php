<?php

namespace gp\admin\Layout;

defined('is_running') or die('Not an entry point...');

class Available extends \gp\admin\Layout{

	public $searchUrl = 'Admin_Theme_Content/Available';


	public function __construct(){
		global $page;
		parent::__construct();

		$page->head_js[] = '/include/js/auto_width.js';
	}


	/**
	 * Show available themes and style variations
	 *
	 */
	public function ShowAvailable(){

		$cmd = \common::GetCommand();

		switch($cmd){
			case 'preview':
			case 'preview_iframe':
			case 'newlayout':
			case 'addlayout':
				if( $this->NewLayout($cmd) ){
					return;
				}
			break;
		}


		$this->GetAddonData();

		$this->ShowHeader();

		$this->AvailableList();

		$this->InvalidFolders();
	}


	public function AvailableList( $show_options = true ){
		global $langmessage, $config;

		//search settings
		$this->searchPerPage = 10;
		$this->searchOrderOptions = array();
		$this->searchOrderOptions['modified']		= $langmessage['Recently Updated'];
		$this->searchOrderOptions['rating_score']	= $langmessage['Highest Rated'];
		$this->searchOrderOptions['downloads']		= $langmessage['Most Downloaded'];

		$this->SearchOrder();


		// get addon information for ordering
		\admin_tools::VersionData($version_data);
		$version_data = $version_data['packages'];

		// combine remote addon information
		foreach($this->avail_addons as $theme_id => $info){

			if( isset($info['id']) ){
				$id = $info['id'];

				if( isset($version_data[$id]) ){
					$info = array_merge($info,$version_data[$id]);
					$info['rt'] *= 5;
				}

				//use local rating
				if( isset($this->addonReviews[$id]) ){
					$info['rt'] = $this->addonReviews[$id]['rating'];
				}
			}else{
				$info['rt'] = 6; //give local themes a high rating to make them appear first, rating won't actually display
			}

			$info += array( 'dn'=>0, 'rt'=>0 );

			//modified time
			if( !isset($info['tm']) ){
				$info['tm'] = self::ModifiedTime( $info['full_dir'] );
			}


			$this->avail_addons[$theme_id] = $info;
		}


		// sort by
		uasort( $this->avail_addons, array($this,'SortUpdated') );
		switch($this->searchOrder){

			case 'downloads':
				uasort( $this->avail_addons, array($this,'SortDownloads') );
			break;

			case 'modified':
				uasort( $this->avail_addons, array($this,'SortRating') );
				uasort( $this->avail_addons, array($this,'SortUpdated') );
			break;

			case 'rating_score':
			default:
				uasort( $this->avail_addons, array($this,'SortRating') );
			break;
		}

		// pagination
		$this->searchMax = count($this->avail_addons);
		if( isset($_REQUEST['page']) && ctype_digit($_REQUEST['page']) ){
			$this->searchPage = $_REQUEST['page'];
		}

		$start = $this->searchPage * $this->searchPerPage;
		$possible = array_slice( $this->avail_addons, $start, $this->searchPerPage, true);


		if( $show_options ){
			$this->SearchOptions();
		}


		// show themes
		echo '<div id="gp_avail_themes">';
		foreach($possible as $theme_id => $info){
			$theme_label = str_replace('_',' ',$info['name']);
			$version = '';
			$id = false;
			if( isset($info['version']) ){
				$version = $info['version'];
			}
			if( isset($info['id']) && is_numeric($info['id']) ){
				$id = $info['id'];
			}

			$has_screenshot = file_exists($info['full_dir'].'/screenshot.png');

			//screenshot
			if( $has_screenshot ){
				echo '<div class="expand_child_click">';
				echo '<b class="gp_theme_head">'.$theme_label.' '.$version.'</b>';
				echo '<div style="background-image:url(\''.\common::GetDir($info['rel'].'/screenshot.png').'\')">';
			}else{
				echo '<div>';
				echo '<b class="gp_theme_head">'.$theme_label.' '.$version.'</b>';
				echo '<div>';
			}

			//options
			echo '<div class="gp_theme_options">';

				//colors
				echo '<b>'.$langmessage['preview'].'</b>';
				echo '<ul>';
				foreach($info['colors'] as $color){
					echo '<li>';
					$q = 'cmd=preview&theme='.rawurlencode($theme_id.'/'.$color).$this->searchQuery;
					if( $this->searchPage ){
						$q .= '&page='.$this->searchPage;
					}
					echo \common::Link('Admin_Theme_Content/Available',str_replace('_','&nbsp;',$color),$q);
					echo '</li>';
				}
				echo '</ul>';



				ob_start();
				if( $id ){

					//more info
					echo '<li>'.$this->DetailLink('theme', $id,'More Info...').'</li>';


					//support
					$forum_id = 1000 + $id;
					echo '<li><a href="'.addon_browse_path.'/Forum?show=f'.$forum_id.'" target="_blank">'.$langmessage['Support'].'</a></li>';

					//rating
					$rating = 0;
					if( $info['rt'] > 0 ){
						$rating = $info['rt'];
					}
					echo '<li><span class="nowrap">'.$langmessage['rate'].' '.$this->ShowRating($info['rel'],$rating).'</span></li>';


					//downloads
					if( $info['dn'] > 0 ){
						echo '<li><span class="nowrap">Downloads: '.number_format($info['dn']).'</span></li>';
					}
				}

				//last updated
				if( $info['tm'] > 0 ){
					echo '<li><span class="nowrap">'.$langmessage['Modified'].': ';
					echo \common::date($langmessage['strftime_datetime'],$info['tm']);
					echo '</span></li>';
				}



				if( $info['is_addon'] ){

					//delete
					$folder = $info['folder'];
					$title = sprintf($langmessage['generic_delete_confirm'], $theme_label );
					$attr = array( 'data-cmd'=>'cnreq','class'=>'gpconfirm','title'=> $title );
					echo '<li>'.\common::Link('Admin_Theme_Content',$langmessage['delete'],'cmd=deletetheme&folder='.rawurlencode($folder),$attr).'</li>';

					//order
					if( isset($config['themes'][$folder]['order']) ){
						echo '<li>Order: '.$config['themes'][$folder]['order'].'</li>';
					}
				}


				$options = ob_get_clean();

				if( !empty($options) ){
					echo '<b>'.$langmessage['options'].'</b>';
					echo '<ul>';
					echo $options;
					echo '</ul>';
				}

			echo '</div></div>';

			//remote upgrade
			if( gp_remote_themes && $id && isset(\admin_tools::$new_versions[$id]) && version_compare(\admin_tools::$new_versions[$id]['version'], $version ,'>') ){
				$version_info = \admin_tools::$new_versions[$id];
				echo \common::Link('Admin_Theme_Content',$langmessage['new_version'],'cmd=remote_install&id='.$id.'&name='.rawurlencode($version_info['name']));
			}


			echo '</div>';
		}
		echo '</div>';


 		if( $show_options ){
			$this->SearchNavLinks();
		}

	}


	public static function ModifiedTime($directory){

		$files = scandir( $directory );
		$time = filemtime( $directory );
		foreach($files as $file){
			if( $file == '..' || $file == '.' ){
				continue;
			}

			$full_path = $directory.'/'.$file;

			if( is_dir($full_path) ){
				$time = max( $time, self::ModifiedTime( $full_path ) );
			}else{
				$time = max( $time, filemtime( $full_path ) );
			}
		}
		return $time;
	}

	public function SortDownloads($a,$b){
		return $b['dn'] > $a['dn'];
	}
	public function SortRating($a,$b){
		return $b['rt'] > $a['rt'];
	}
	public function SortUpdated($a,$b){
		return $b['tm'] > $a['tm'];
	}


	/**
	 * Manage adding new layouts
	 *
	 */
	public function NewLayout($cmd){
		global $langmessage;

		//check the requested theme
		$theme =& $_REQUEST['theme'];
		$theme_info = $this->ThemeInfo($theme);
		if( $theme_info === false ){
			message($langmessage['OOPS'].' (Invalid Theme)');
			return false;
		}


		// three steps of installation
		switch($cmd){

			case 'preview':
				if( $this->PreviewTheme($theme, $theme_info) ){
					return true;
				}
			break;

			case 'preview_iframe':
				$this->PreviewThemeIframe($theme,$theme_info);
			return true;

			case 'newlayout':
				$this->NewLayoutPrompt($theme, $theme_info);
			return true;

			case 'addlayout':
				$this->AddLayout($theme_info);
			break;
		}
		return false;
	}


	/**
	 * Preview a theme and give users the option of creating a new layout
	 *
	 */
	public function PreviewTheme($theme, $theme_info){
		global $langmessage,$config,$page;

		$theme_id = dirname($theme);
		$color = $theme_info['color'];


		$_REQUEST += array('gpreq' => 'body'); //force showing only the body as a complete html document
		$page->get_theme_css = false;
		$page->show_admin_content = false;
		$page->get_theme_css = false;

		$page->head_js[] = '/include/js/auto_width.js';


		ob_start();

		//new
		echo '<div id="theme_editor">';
		echo '<div class="gp_scroll_area">';


		echo '<div>';
		echo \common::Link('Admin_Theme_Content/Available','&#171; '.$langmessage['available_themes']);
		echo \common::Link('Admin_Theme_Content/Available',$langmessage['use_this_theme'],'cmd=newlayout&theme='.rawurlencode($theme),'data-cmd="gpabox" class="add_layout"');
		echo '</div>';


		echo '<div class="separator"></div>';


		$this->searchUrl = 'Admin_Theme_Content/Available';
		$this->AvailableList( false );

		//search options
		$this->searchQuery .= '&cmd=preview&theme='.rawurlencode($theme);
		$this->SearchOptions( false );

		echo '</div>';


		//show site in iframe
		echo '<div id="gp_iframe_wrap">';
		$url = \common::GetUrl('Admin_Theme_Content/Available','cmd=preview_iframe&theme='.rawurlencode($theme));
		echo '<iframe src="'.$url.'" id="gp_layout_iframe" name="gp_layout_iframe"></iframe>';
		echo '</div>';

		echo '</div>';
		$page->admin_html = ob_get_clean();
		return true;
	}


	public function PreviewThemeIframe($theme, $theme_info){
		global $langmessage,$config,$page;

		\admin_tools::$show_toolbar = false;

		$theme_id = dirname($theme);
		$template = $theme_info['folder'];
		$color = $theme_info['color'];
		$display = htmlspecialchars($theme_info['name'].' / '.$theme_info['color']);
		$display = str_replace('_',' ',$display);
		$this->LoremIpsum();
		$page->gpLayout = false;
		$page->theme_name = $template;
		$page->theme_color = $color;
		$page->theme_dir = $theme_info['full_dir'];
		$page->theme_rel = $theme_info['rel'].'/'.$color;

		if( isset($theme_info['id']) ){
			$page->theme_addon_id = $theme_info['id'];
		}

		$page->theme_path = \common::GetDir($theme_info['rel'].'/'.$color);

		$page->show_admin_content = false;
	}


	/**
	 * Give users a few options before creating the new layout
	 *
	 */
	public function NewLayoutPrompt($theme, $theme_info ){
		global $langmessage;


		$label = substr($theme_info['name'].'/'.$theme_info['color'],0,25);

		echo '<h2>'.$langmessage['new_layout'].'</h2>';
		echo '<form action="'.\common::GetUrl('Admin_Theme_Content/Available').'" method="post">';
		echo '<table class="bordered full_width">';

		echo '<tr><th colspan="2">';
		echo $langmessage['options'];
		echo '</th></tr>';

		echo '<tr><td>';
		echo $langmessage['label'];
		echo '</td><td>';
		echo '<input type="text" name="label" value="'.htmlspecialchars($label).'" class="gpinput" />';
		echo '</td></tr>';

		echo '<tr><td>';
		echo $langmessage['make_default'];
		echo '</td><td>';
		echo '<input type="checkbox" name="default" value="default" />';
		echo '</td></tr>';

		echo '</table>';

		echo '<p>';
		echo '<input type="hidden" name="theme" value="'.htmlspecialchars($theme).'" /> ';
		echo '<button type="submit" name="cmd" value="addlayout" class="gpsubmit">'.$langmessage['save'].'</button> ';
		echo '<input type="button" name="" value="Cancel" class="admin_box_close gpcancel"/> ';
		echo '</p>';
		echo '</form>';
	}


	/**
	 * Add a new layout to the installation
	 *
	 */
	public function AddLayout($theme_info){
		global $gpLayouts, $langmessage, $config, $page;

		$new_layout = array();
		$new_layout['theme'] = $theme_info['folder'].'/'.$theme_info['color'];
		$new_layout['color'] = self::GetRandColor();
		$new_layout['label'] = htmlspecialchars($_POST['label']);
		if( $theme_info['is_addon'] ){
			$new_layout['is_addon'] = true;
		}


		includeFile('admin/admin_addon_installer.php');
		$installer = new \admin_addon_installer();
		$installer->addon_folder_rel = dirname($theme_info['rel']);
		$installer->code_folder_name = '_themes';
		$installer->source = $theme_info['full_dir'];
		$installer->new_layout = $new_layout;
		if( !empty($_POST['default']) && $_POST['default'] != 'false' ){
			$installer->default_layout = true;
		}

		$success = $installer->Install();
		$installer->OutputMessages();

		if( $success && $installer->default_layout ){
			$page->SetTheme();
			$this->SetLayoutArray();
		}
	}

}