<?php
/* 
Plugin Name: Change Image Type
Plugin URI: http://www.kansai-ssol.co.jp/
Description: Change Image Type Plugin
Version: 1 
Author: Kansai System Solutions, Asada
Author URI: http://www.kansai-ssol.co.jp/
*/


/*****************************************************************************
 *  Change Image Type
 *
 *       Corp: KSSOL, INC.
 *
 *       date: 2010/08/17
 *  ProgramID: changeimagetype
 * Functional: Modifications of the image file format.
 *    $Author: ks-sol asada $
 *   $RCSfile: $
 *  $Revision: 1.0 $
 *      $Date: 2010/08/17 00:00:00 $
 *     $State: Exp $
 *
 *****************************************************************************/

add_action('admin_menu', 'changeimagetype_menu');

$changeimagetype_dirname = plugin_basename(dirname(__FILE__));
$changeimagetype_locale_dir = PLUGINDIR . '/' . $changeimagetype_dirname . '/languages';
load_plugin_textdomain('changeimagetype', $changeimagetype_locale_dir);

function changeimagetype_menu() {
	add_media_page(__('Changing image format', 'changeimagetype')
	             , __('Changing image format', 'changeimagetype')
	             , 8
	             , __FILE__
	             , 'changeimagetype_init'
	);
}

function changeimagetype_init()
{
	if (!function_exists('gd_info')) {
		printf('<div class="wrap"><p>%s</p></div>', __('Sorry, GD library is not available, you can not continue.', 'changeimagetype'));
		return false;
	}
	$obj = new clsChangeImageType;
	$obj->do_process();
}

class clsChangeImageType
{
	var $ds = DIRECTORY_SEPARATOR;

	/**
	 * constractor
	 **
	 * access:public
	 * param :none
	 * return:none
	 */
	function clsChangeImageType()
	{
	}

	/**
	 * main process
	 **
	 * access:public
	 * param :none
	 * return:none
	 */
	function do_process()
	{
		global $wpdb;
		$message_tpl = '<div class="wrap"><p>%s</p></div>';

		// Registration process
		if ((isset($_POST['btnChg']['renew']) || isset($_POST['btnChg']['rep'])) &&
		    isset($_POST['id']) && isset($_POST['type']))
		{
			include_once('includes'. $this->ds. 'changeImage.php');

			$fullPath = get_attached_file($_POST['id']);
			$baseFileName = basename($fullPath);
			$pos = strrpos($baseFileName, '.');

			$arrFile['path'] = dirname($fullPath);
			$arrFile['old'] = array(
				  'file' => substr($baseFileName, 0, $pos)
				, 'type' => substr($baseFileName, $pos+1)
			);
			$arrFile['new'] = array(
				  'file' => $this->getCopyFileName($arrFile['path'], $baseFileName, $_POST['type'])
				, 'type' => $_POST['type']
			);

			// Image reproduction
			changeImage($fullPath, $_POST['type'], $arrFile['new']['file']. '.'. $arrFile['new']['type']);

			list($guidPath, ) = wp_get_attachment_image_src($_POST['id']);
			$guidPath = dirname($guidPath);
			$attachment = array(
				  'post_mime_type' => $this->getMimeType($arrFile['new']['type'])
				, 'guid' => $guidPath. '/'. $arrFile['new']['file']. '.'. $arrFile['new']['type']
				, 'post_parent' => 0
				, 'post_title' => $arrFile['new']['file']
				, 'post_content' => null
			);
			$file = $arrFile['path']. '/'. $arrFile['new']['file']. '.'. $arrFile['new']['type'];

			$id = wp_insert_attachment($attachment, $file);
			if ( !is_wp_error($id) ) {
				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
			}

			if (!$this->fncUpdateMediaValues($_POST['id'], $id)) {
				printf($message_tpl, __('Were unable to reproduce the original image settings duplicate the image settings.', 'changeimagetype'));
			}

			if (isset($_POST['btnChg']['renew'])) {
				wp_delete_attachment($_POST['id']);
			}
		}

		$arrMimeType = array('image/bmp', 'image/jpeg', 'image/gif', 'image/png');
		$sql = <<< END_OF_VAR
SELECT ID
  FROM $wpdb->posts
 WHERE post_type = 'attachment'
   and post_mime_type IN('%s')
order by post_date desc
END_OF_VAR;

		if ( ($lost = $wpdb->get_col(sprintf($sql, implode('\',\'', $arrMimeType)))) === false ) {
			printf($message_tpl, __('Failed to retrieve data.', 'changeimagetype'));
		}

		if (($mediaCnt = count($lost)) == 0) {
			printf($message_tpl, __('There is no target file.', 'changeimagetype'));
			return;
		}

		// get image media list
		$sql = <<< END_OF_VAR
SELECT post.ID
     , post.post_author
     , user.user_nicename
     , post.post_date
     , post.post_title
     , post.post_mime_type
     , post.guid
  FROM $wpdb->posts as post
       INNER JOIN $wpdb->users as user on (post.post_author = user.ID)
 WHERE post.ID IN(%s)
order by post.post_date desc
END_OF_VAR;
		$page = isset($_GET['paged']) ? $_GET['paged'] : 1;
		$lim = ($page * 20 < $mediaCnt) ? 20 : $mediaCnt % 20;

		$sql_id = '';
		for ($i = ($page - 1) * 20; $i < ($page - 1) * 20 + $lim; $i++) {
			$sql_id .= $lost[$i]. ',';
		}
		$rowData = $wpdb->get_results(sprintf($sql, substr($sql_id, 0, strlen($sql_id) - 1)));

		// make body
		$btnChgRenew_lbl = __('Conversion', 'changeimagetype');
		$btnChgRep_lbl = __('Replication & transformation', 'changeimagetype');
		$body_tpl = <<< END_OF_VAR
<tr valign="top" class="%s">
	<td>%s</td>
	<td><strong>%s</strong><p>%s</p></td>
	<td>
		<select id="sltType_%d">%s</select>
		<div>
		<!--input type='button' name='btnChg[renew]' value='$btnChgRenew_lbl' class='button' onclick="fncOnSubmit(this, %d, %s, '%s')"-->
		<input type='button' name='btnChg[rep]' value='$btnChgRep_lbl' class='button' onclick="fncOnSubmit(this, %d, %s, '%s')">
		</div>
	</td>
	<td>%s</td>
	<td>%s</td>
</tr>

END_OF_VAR;
		$msg_renew = __('Converts the specified image format.\nAre you sure?', 'changeimagetype');
		$msg_rep = __('Make a copy of the image. Then, convert the format.\nAre you sure?', 'changeimagetype');
		$body = '';
		$evanflg = false;
		foreach ($rowData as $key => $arr) {
			$body .= sprintf($body_tpl, ($evanflg = !$evanflg) ? 'alternate' : ''
			                          , $this->getAttachmentImage($arr)
			                          , $arr->post_title
			                          , $this->dispImageType($arr->post_mime_type)
			                          , $arr->ID
			                          , $this->getTypeSelect($arr->post_mime_type)
			                          , $arr->ID
			                          , sprintf('document.getElementById(\'sltType_%1$d\').options[document.getElementById(\'sltType_%1$d\').selectedIndex].text', $arr->ID)
			                          , $msg_renew
			                          , $arr->ID
			                          , sprintf('document.getElementById(\'sltType_%1$d\').options[document.getElementById(\'sltType_%1$d\').selectedIndex].text', $arr->ID)
			                          , $msg_rep
			                          , $arr->user_nicename
			                          , $this->displayDate($arr)
			);
		}

		print('<div class="wrap">');

		// display title
		screen_icon();
		$title = __('Changing image format', 'changeimagetype');
		print("<div class=\"wrap\"><h2>$title</h2></div>");

		// display pager
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'paged', '%#%' ),
			'format' => '',
			'prev_text' => __('&laquo;'),
			'next_text' => __('&raquo;'),
			'total' => ceil($mediaCnt / 20),
			'type' => 'plain',
			'current' => isset($_GET['paged']) ? $_GET['paged'] : 1
		));
		$displaying_tpl = __('Displaying %d&#8211;%d of %d', 'changeimagetype');
		$tpl = <<< END_OF_VAR
<div class="tablenav">
<div class='tablenav-pages'><span class="displaying-num">$displaying_tpl</span>%s</div>
</div>
END_OF_VAR;
		if ( $page_links ) {
			printf($tpl, ($page - 1) * 20 + 1
			           , ($page - 1) * 20 + $lim
			           , $mediaCnt
			           , $page_links
			);
		}

		// display table
		printf($this->getMakeTableTpl()
		     , $body
		);

		if ( $page_links ) {
			printf($tpl, ($page - 1) * 20 + 1
			           , ($page - 1) * 20 + $lim
			           , $mediaCnt
			           , $page_links
			);
		}

		print('</div>');

		// display script
		$this->scripts();
	}

	/**
	 * Making table template
	 **
	 * access:public
	 * param :none
	 * return:string
	 */
	function getMakeTableTpl()
	{
		$file = _x('File', 'column name');
		$author = _x('Author', 'media column name');
		$date = _x('Date', 'column name');

		return <<< END_OF_VAR
<table class="widefat">
<thead>
<tr>
	<th> </th>
	<th>$file</th>
	<th> </th>
	<th>$author</th>
	<th>$date</th>
</tr>
</thead>
<tfoot>
<tr>
	<th> </th>
	<th>$file</th>
	<th> </th>
	<th>$author</th>
	<th>$date</th>
</tr>
</tfoot>
<tbody>
%s
</tbody>
</table>
END_OF_VAR;
	}

	/**
	 * Making AttachmentImage
	 **
	 * access:public
	 * param :array $pPost
	 * return:string
	 */
	function getAttachmentImage($pPost)
	{
		if ($pPost->post_mime_type == 'image/bmp') {
			return sprintf('<img width="%d" height="%d" src="%s" class="attachment-80x60" alt="%s" title="%s" />', 80
			                                                                                                     , 60
			                                                                                                     , $pPost->guid
			                                                                                                     , $pPost->post_title
			                                                                                                     , $pPost->post_title
			);
		} else {
			return wp_get_attachment_image( $pPost->ID, array(80, 60), true );
		}
	}

	/**
	 * Get image type
	 **
	 * access:public
	 * param :string $str
	 * return:string
	 */
	function getTypeSelect($str)
	{
		$arrSelect = array(
			  'image/jpeg' => '<option>jpg</option>'
			, 'image/gif' => '<option>gif</option>'
			, 'image/png' => '<option>png</option>'
		);
		$strSelect = '';
		foreach ($arrSelect as $key => $val) {
			if ($str == $key) {
				continue;
			}
			$strSelect .= $val;
		}
		return $strSelect;
	}

	/**
	 * Get the extension for display
	 **
	 * access:public
	 * param :string $str
	 * return:string
	 */
	function dispImageType($str)
	{
		return strtoupper( str_replace( 'image/', '', $str ) );
	}

	/**
	 * Get the date for display
	 **
	 * access:public
	 * param :array $pPost
	 * return:string
	 */
	function displayDate($pPost)
	{
		if ( '0000-00-00 00:00:00' == $pPost->post_date ) {
			$t_time = $h_time = __('Unpublished');
		} else {
			$t_time = get_the_time(__('Y/m/d g:i:s A'));
			$m_time = $pPost->post_date;
			$time = get_post_time( 'G', true, $pPost->ID, false );
			if ( ( abs($t_diff = time() - $time) ) < 86400 ) {
				if ( $t_diff < 0 )
					$h_time = sprintf( __('%s from now'), human_time_diff( $time ) );
				else
					$h_time = sprintf( __('%s ago'), human_time_diff( $time ) );
			} else {
				$h_time = mysql2date(__('Y/m/d'), $m_time);
			}
		}
		return $h_time;
	}

	/**
	 * Insert Script
	 **
	 * access:public
	 * param :none
	 * return:none
	 */
	function scripts()
	{
		wp_enqueue_script('prototype');
		wp_enqueue_script('changeimagetype', '/'. PLUGINDIR. '/'. plugin_basename(dirname(__FILE__)). '/js/changeimagetype.js');
		wp_enqueue_script('postsubmit', '/'. PLUGINDIR. '/'. plugin_basename(dirname(__FILE__)). '/js/postsubmit.js');
		wp_head();
	}

	/**
	 * Getting duplicate image name
	 **
	 * access:public
	 * param :string $pDirPath
	 *       :string $pFileName
	 *       :string $pType
	 * return:string
	 */
	function getCopyFileName($pDirPath, $pFileName, $pType)
	{
		$pos = strrpos($pFileName, '.');
		$newFileName = $newBaseFileName = substr($pFileName, 0, $pos);
		$type = substr($pFileName, $pos+1);
		$number = 1;
		while (file_exists($pDirPath. $this->ds. $newFileName. '.'. $pType)) {
			$newFileName = $newBaseFileName. '_'. $number;
			$number++;
		}
		return $newFileName;
	}

	/**
	 * Update the value of media
	 **
	 * access:public
	 * param :integer $pOldId
	 *       :integer $pNewId
	 * return:boolean
	 */
	function fncUpdateMediaValues($pOldId, $pNewId)
	{
		global $wpdb;

		list($guidPath, ) = wp_get_attachment_image_src($pId);
		$guidPath = dirname($guidPath);

		// Transaction start
		@mysql_query("BEGIN", $wpdb->dbh);

		$sql = <<< END_OF_VAR
UPDATE $wpdb->posts AS a
      ,(SELECT post_content
              ,post_title
              ,post_excerpt
          FROM $wpdb->posts
         WHERE ID = %d
       ) AS b
   SET a.post_content = b.post_content
      ,a.post_title = b.post_title
      ,a.post_excerpt = b.post_excerpt
 WHERE ID = %d
END_OF_VAR;
		$sql = sprintf($sql, $pOldId
		                   , $pNewId
		);
		if ($wpdb->query($sql) === false) {
			@mysql_query("ROLLBACK", $wpdb->dbh);
			return false;
		}

		$sql = <<< END_OF_VAR
INSERT INTO $wpdb->postmeta (
       post_id
     , meta_key
     , meta_value
) SELECT %d
       , '_wp_attachment_image_alt'
       , meta_value
   FROM $wpdb->postmeta
  WHERE post_id = %d
    AND meta_key = '_wp_attachment_image_alt'
END_OF_VAR;

		$sql = sprintf($sql, $pNewId
		                   , $pOldId
		);

		if ($wpdb->query($sql) === false) {
			@mysql_query("ROLLBACK", $wpdb->dbh);
			return false;
		}

		// commit
		@mysql_query("COMMIT", $wpdb->dbh);
		return true;
	}

	/**
	 * Get the mime type
	 **
	 * access:public
	 * param :string $pType
	 * return:string
	 */
	function getMimeType($pType)
	{
		$arrMimeType = array('bmp' => 'image/bmp'
		                   , 'jpg' => 'image/jpeg'
		                   , 'gif' => 'image/gif'
		                   , 'png' => 'image/png'
		);
		if (!isset($arrMimeType[$pType])) {
			return null;
		}
		return $arrMimeType[$pType];
	}
}

?>