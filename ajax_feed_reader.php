<?php
/*
Plugin Name: Ajax Feed Reader
Plugin URI: https://github.com/uhowww/Ajax-Feed-Reader
Description: Feed reader plug-in.
Version: 2
Author: Takumi Kumagai
Author URI: http://taku-min.net/
License: GPL2
*/

class Ajax_Feed_Reader{

	public function __construct(){
		add_action('wp_ajax_nopriv_AjaxFeedReader2', array($this,'return_json'));
		add_action('wp_ajax_AjaxFeedReader2',array($this,'return_json'));
		add_shortcode('AFR',array($this,'set_shortcode_AFR'));
	}

	/**
	 * SimplePieのキャッシュ機能を使う
	 * @param $feed
	 * @return mixed
	 */
	public function cache($feed){
		$cache_dir = WP_CONTENT_DIR.'/cache/ajax_feed';
		$feed->set_cache_location($cache_dir);

		if(is_writable(WP_CONTENT_DIR)){

			if(!is_dir($cache_dir)){
				wp_mkdir_p($cache_dir);
			}elseif(!is_writable($cache_dir)){
				//何故かajax_feedのキャッシュディレクトリが書き込めないのでキャッシュを諦める
				$feed->enable_cache(false);
			}

		}else{
			//wp_contentに書き込みできなければキャッシュを作らない
			$feed->enable_cache(false);
		}
		return $feed;
	}

	public function return_json(){
		if(empty($_POST['AFRurl'])){
			return;
		}

		$simplepie = ABSPATH.WPINC.'/class-simplepie.php';
		if(file_exists($simplepie)){
			/** @noinspection PhpIncludeInspection */
			require_once($simplepie);
		}else{
			wp_die();
		}

		$url_list = explode(',',$_POST['AFRurl']);
		$return = array();

		$feed = new SimplePie();
		$feed->set_feed_url($url_list);
		$feed->force_feed(true);

		if($_POST['AFRlimit']){
			$feed->set_item_limit(intval($_POST['AFRlimit']));
		}

		//キャッシュ
		$feed = $this->cache($feed);

		$feed->init();

		if(count($feed->error)){
			$return['error'] = $feed->error;
		}else{
			foreach($feed->get_items() as $item){
				$return[] = array(
					'link' => $item->get_permalink(),
					'title' => $item->get_title(),
					'date' => $item->get_date('Y.m.j'),
					'description' => $item->get_description(),
					'description_notag' => strip_tags($item->get_description()),
					'content' => $item->get_content(),
					'content_notag' => strip_tags($item->get_content()),

				);
			}
		}
		header('Content-Type: application/json; charset='. get_bloginfo('charset'));
		echo json_encode($return);
		die();
	}

	/**
	 * @param $att
	 * @return string
	 */
	public function set_shortcode_AFR($att){
		$url=$limit=null;
		extract(
			shortcode_atts(
				array(
					'url' => '',
					'limit' => '',
				), $att));
		if($url!=''){
			$divid = 'afr'.rand(100,999);

			$json_url = admin_url('admin-ajax.php');
			$request_url = str_replace(array("\r\n","\r","\n"),'',strip_tags($url));

			$return = <<<HTML
<div class="AFR" id="$divid"></div>
<script>
jQuery(function($){
	$('#$divid').html('<span class="loading">フィード読み込み中</span>');
	$.ajax({
		type: 'POST',
		url: '{$json_url}',
		data: {
			"action": "AjaxFeedReader2",
			"AFRurl" : "{$request_url}",
			"AFRlimit" : "{$limit}"
		},
		success: function(data){

			if(data.length <= 0){
				$('#$divid').html('読み込めませんでした');
				return false;
			}
			if(data['error']){
				$('#$divid').html(data['error']);
				return false;
			}

			var source = '<dl>';
			jQuery.each(data,function(){
				source += '<dt>';

				if(this.link){
					source += '<a href="'+this.link+'">';
				}
				source += this.title;
				if(this.link){
					source += '</a>';
				}
				source += '<span class="date">'+this.date+'</span>';
				source += '</dt>';


				source += '<dd>';
				source += this.description;
				source += this.content_notag;
				source += '</dd>';


			});
			source += '</dl>';
			$('#$divid').html(source);
		},
		error: function(data){
			$('#$divid').html('接続エラー');
			console.log(data);
		}
	});
});
</script>
HTML;
			return $return;
		}
	}

}
$Ajax_Feed_Reader = new Ajax_Feed_Reader();