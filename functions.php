<?php
	
	function read_config()
	{
		$config=array();
		
		$contents=file_get_contents('config.txt');
		$lines=explode("\n", $contents);
		
		foreach ($lines as $line) {
			$content=explode('#', $line);
			$fields=explode('=', trim($content[0]));
			if (count($fields)==2) {
				if (is_numeric(strpos($fields[0], '.'))) {
					$parts=explode('.', $fields[0]);
					$config[$parts[0]][$parts[1]]=$fields[1];
				} else {
					$config[$fields[0]]=$fields[1];
				}
			}
		}
		
		return $config;
	}
	
	function json_rpc_send($host, $port, $secure, $user, $password, $method, $params=array(), &$rawresponse=false)
	{
		if (!function_exists('curl_init')) {
			output_html_error('This web demo requires the curl extension for PHP. Please contact your web hosting provider or system administrator for assistance.');
			exit;
		}
		
		$url=($secure ? 'https' : 'http').'://'.$host.':'.$port.'/';
				
		$payload=json_encode(array(
			'id' => time(),
			'method' => $method,
			'params' => $params,
		));
		
	//	echo '<PRE>'; print_r($payload); echo '</PRE>';
		
		$ch=curl_init($url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERPWD, $user.':'.$password);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: '.strlen($payload)
		));
		
		$response=curl_exec($ch);
		
		if ($rawresponse!==false)
			$rawresponse=$response;
		
	//	echo '<PRE>'; print_r($response); echo '</PRE>';
				
		$result=json_decode($response, true);
		
		if (!is_array($result)) {
			$info=curl_getinfo($ch);
			$result=array('error' => array(
				'code' => 'HTTP '.$info['http_code'],
				'message' => strip_tags($response).' '.$url
			));
		}
		
		return $result;
	}
	
	function set_iridiun_chain($chain)
	{
		global $iridiun_chain;
		
		$iridiun_chain=$chain;
	}
	
	function iridiun($method) // other params read from func_get_args()
	{
		global $iridiun_chain;
		
		$args=func_get_args();
		
		return json_rpc_send($iridiun_chain['rpchost'], $iridiun_chain['rpcport'], $iridiun_chain['rpcsecure'],
			$iridiun_chain['rpcuser'], $iridiun_chain['rpcpassword'], $method, array_slice($args, 1));
	}
	
	function iridiun_with_raw(&$rawresponse, $method) // other params read from func_get_args()
	{
		global $iridiun_chain;
		
		$args=func_get_args();
		$rawresponse='';
		
		return json_rpc_send($iridiun_chain['rpchost'], $iridiun_chain['rpcport'], $iridiun_chain['rpcsecure'],
			$iridiun_chain['rpcuser'], $iridiun_chain['rpcpassword'], $method, array_slice($args, 2), $rawresponse);
	}
	
	function output_html_error($html)
	{
		echo '<div class="bg-danger" style="padding:1em;">Error: '.$html.'</div>';
	}
	
	function output_rpc_error($error)
	{
		output_html_error(html($error['code']).'<br/>'.html($error['message']));
	}
	
	function output_success_text($success)
	{
		echo '<div class="bg-success" style="padding:1em;">'.nl2br(html($success)).'</div>';
	}
	
	function output_error_text($error)
	{
		echo '<div class="bg-danger" style="padding:1em;">'.nl2br(html($error)).'</div>';
	}
	
	function no_displayed_error_result(&$result, $response)
	{
		if (is_array($response['error'])) {
			$result=null;
			output_rpc_error($response['error']);
			return false;
		
		} else {
			$result=$response['result'];
			return true;
		}
	}
	
	function html($string)
	{
		return htmlspecialchars($string);
	}
	
	function chain_page_url_html($chain, $page=null, $params=array())
	{
		$url='./?chain='.$chain;
		
		if (strlen($page))
			$url.='&page='.$page;
			
		foreach ($params as $key => $value)
			$url.='&'.rawurlencode($key).'='.rawurlencode($value);
			
		return html($url);
	}
	
	function array_get_column($array, $key) // see array_column() in recent versions of PHP
	{
		$result=array();
		
		foreach ($array as $index => $element)
			if (array_key_exists($key, $element))
				$result[$index]=$element[$key];
		
		return $result;
	}
	
	function iridiun_getinfo()
	{
		global $iridiun_getinfo;
		
		if (!is_array($iridiun_getinfo))
			no_displayed_error_result($iridiun_getinfo, iridiun('getinfo'));
		
		return $iridiun_getinfo;
	}
	
	function iridiun_has_protocol($version)
	{
		$getinfo=iridiun_getinfo();
		
		return $getinfo['protocolversion']>=$version;
	}
		
	function iridiun_has_multi_item_keys()
	{
		return iridiun_has_protocol(20001);
	}
	
	function iridiun_has_json_text_items()
	{
		return iridiun_has_protocol(20001);
	}
	
	function iridiun_has_custom_permissions()
	{
		return iridiun_has_protocol(20003);
	}
	
	function iridiun_has_off_chain_items()
	{
		return iridiun_has_protocol(20003);
	}
	
	function iridiun_has_smart_filters()
	{
		return iridiun_has_protocol(20004);
	}
	
	function iridiun_labels()
	{
		global $iridiun_labels;
		
		if (!is_array($iridiun_labels)) {
			if (no_displayed_error_result($items, iridiun('liststreampublishers', 'root-stream', '*', true, 10000))) {
				$iridiun_labels=array();
				foreach ($items as $item)
					$iridiun_labels[$item['publisher']]=pack('H*', $item['last']['data']);
			}
		}
		
		return $iridiun_labels;
	}
	
	function iridiun_max_data_size()
	{
		global $iridiun_max_data_size;
		
		if (!isset($iridiun_max_data_size))
			if (no_displayed_error_result($params, iridiun('getblockchainparams')))
				$iridiun_max_data_size=min(
					$params['maximum-block-size']-80-320,
					$params['max-std-tx-size']-320,
					$params['max-std-op-return-size']
				);
		
		return $iridiun_max_data_size;
	}	
	
	function format_address_html($address, $local, $labels, $link=null)
	{
		$label=@$labels[$address];
		
		if (strlen($link)) {
			$prefix='<a href="'.html($link).'">';
			$suffix='</a>';
		} else {
			$prefix='';
			$suffix='';
		}
		
		if (isset($label))
			$string=html($label).' ('.$prefix.html($address).$suffix.($local ? ', local' : '').')';
		else
			$string=$prefix.html($address).$suffix.($local ? ' (local)' : '');
			
		return $string;
	}
	
	function string_to_txout_bin($string)
	{
		return ltrim($string, "\x00"); // ensures that first byte 0x00 means it's a file
	}
	
	function file_to_txout_bin($filename, $mimetype, $content)
	{
		return "\x00".$filename."\x00".$mimetype."\x00".$content;
	}
	
	function txout_bin_to_file($data)
	{
		$parts=explode("\x00", $data, 4);
		
		if ( (count($parts)!=4) || ($parts[0]!='') )
			return null;
		
		return array(
			'filename' => $parts[1],
			'mimetype' => $parts[2],
			'content' => $parts[3],
		);
	}
	
	function fileref_to_string($vout, $filename, $mimetype, $filesize)
	{
		return "\x00".$vout."\x00".$filename."\x00".$mimetype."\x00".$filesize;
	}
	
	function string_to_fileref($string)
	{
		$parts=explode("\x00", $string);
		
		if ( (count($parts)!=5) || ($parts[0]!='') )
			return null;
			
		return array(
			'vout' => $parts[1],
			'filename' => $parts[2],
			'mimetype' => $parts[3],
			'filesize' => $parts[4],
		);
	}
