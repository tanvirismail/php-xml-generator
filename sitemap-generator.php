<?php

	require_once "simple_html_dom.php";

	
	class Sitemap {
		
		public $priority = "1.0";
		public $skip = array ();
		public $scanned = array ();
		public $start = '';
		public $frequency = "daily";
		public $error = '';
		public $scheme = ["http","https","ftp"];
		
		
		public function skip($url)
		{
			if(is_array($url)){
				foreach ($url as $value){
					$this->skip[] = $value;
				}
			}
			$this->skip[] = $value;
			return $this;
		}
		
		public function frequency($var)
		{
			$this->frequency = $var;
			return $this;
		}
		
		public function priority($var)
		{
			$this->priority = $var;
			return $this;
		}
		
		public function url($url)
		{
			$url = filter_var ($url, FILTER_SANITIZE_URL);
			$url = rtrim($url, '/').'/';
			$protocol = '';
			if ((substr ($url, 0, 7) != "http://")  && (substr ($url, 0, 8) != "https://")){
				$protocol = "http://";
			}
			$url = preg_replace('/\s+/', '', trim($protocol.$url));
			$this->start = $url;
			$this->scanned[] = $this->start;
			return $this;
		}
		
		public function sendRequest($url) {
			$ch = curl_init();
			curl_setopt ($ch, CURLOPT_AUTOREFERER, true);
			curl_setopt ($ch, CURLOPT_URL, $url);
			curl_setopt ($ch, CURLOPT_VERBOSE, 1);
			curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
			curl_setopt ($ch, CURLOPT_HEADER, 0);
			curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 5);

			$data = curl_exec($ch);
			$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			
			
			if($code == 200 && !( curl_errno($ch))) {
				$effective = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL );
				
				curl_close( $ch);
				return ['html'=>$data,'effective'=>$effective];
			} 
			curl_close( $ch);
			$this->error = "FAILED TO CONNECT THIS URL: $url";
			return false;
		}
		
		public function filterUrl($rel,$base)
		{
			$rel = ltrim($rel, '/'); 
			$base = rtrim($base, '/'). '/';
			if(strpos($rel,"//") === 0) {
				return "http:".$rel;
			}
			if  (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;
			$first_char = substr ($rel, 0, 1);
			if ($first_char == '#'  || $first_char == '?') return $base . $rel;
			return $base . $rel;
		}
		
		public function scan ($url=false)
		{
			if($url==false) $url = $this->start;
			$data = $this->sendRequest( $url );
			if($data && !empty($data)) {
				if((parse_url($url, PHP_URL_HOST) == parse_url($data['effective'], PHP_URL_HOST)) && $url!=$data['effective']){
					if( !in_array ($data['effective'], $this->scanned) || !in_array ($data['effective'].'/', $this->scanned)  ){
						$this->scanned[] = $data['effective']; 
					}
				}
				$html =  str_get_html( $data['html'] );
				if($html){
					$anchors   = $html->find('a');
					foreach ($anchors as $key => $val) {
						$next_url = $val->href or "";
						$next_url = explode ("#", $next_url)[0];
						if ( !in_array( parse_url($next_url, PHP_URL_SCHEME) ,$this->scheme) )
						{
							if($rel = $this->filterUrl($next_url, $this->start)){
								$next_url = $rel;
							}
						}
						$next_url = filter_var ($next_url, FILTER_SANITIZE_URL);
						if ( parse_url($next_url, PHP_URL_HOST) == parse_url($this->start, PHP_URL_HOST) ) {
							$ignore = false;
							if (!filter_var ($next_url, FILTER_VALIDATE_URL)) {
								$ignore = true;
							}
							if ( in_array ($next_url, $this->scanned) || in_array ($next_url.'/', $this->scanned) ) {
								$ignore = true;
							}
							foreach($this->scheme as $scheme){
								$rebuildUrl = $scheme .'://'. substr ( $next_url, strlen(parse_url($next_url, PHP_URL_SCHEME)) + 3 ) ;
								if ( in_array ( $rebuildUrl  , $this->scanned) || in_array ( $rebuildUrl.'/'  , $this->scanned) ){
									$ignore = true;
								}
							}
							if (isset ($this->skip) && !$ignore) {
								foreach ($this->skip as $v) {
									if (substr ($next_url, 0, strlen ($v)) == $v)
									{
										$ignore = true;
									}
								}
							}
							if (!$ignore) {
								$next_url = preg_replace('/\s+/', '', trim($next_url));
								$this->scanned[] = $next_url;
								$this->scan ( $next_url );
							}
						}
					}
				}
			}
			return $this->scanned;
		}
	
		public function render()
		{
			$urls = $this->scan();
			$result = [];
			foreach($urls as $key=>$value)
			{
				$priority = number_format ( round ( $this->priority / count ( explode( "/", trim ( str_ireplace ( array ("http://", "https://"), "", $value ), "/" ) ) ) + 0.5, 3 ), 1 );
				$result[] = [
					'loc' => $value,
					'changefreq' => $this->frequency,
					'priority' => ($key == '0') ? $this->priority : $priority
				];
			}
			return $result;
		}
		
		public function makeXML()
		{
			$urls = $this->render();
			$xml =  "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
					"<!-- Created with TMIWEB PHP XML Sitemap Generator http://tmiweb.co -->\n" .
					"<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"\n" .
					"        xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n" .
					"        xsi:schemaLocation=\"http://www.sitemaps.org/schemas/sitemap/0.9\n" .
					"        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd\">\n";
			foreach($urls as $value)
			{
				$xml .= "  <url>\n" .
						"    <loc>" . htmlentities ($value['loc']) ."</loc>\n" .
						"    <changefreq>". $value['changefreq'] ."</changefreq>\n" .
						"    <priority>". $value['priority'] ."</priority>\n" .
						"  </url>\n";
			}
			$xml .= "</urlset>\n";
			return $xml;
		}
	
		public function download($filename = 'sitemap.xml')
		{
			header('Content-Description: File Transfer');
			header('Content-Disposition: attachment; filename='.$filename);
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Type: text/xml; charset=utf-8');
			
			$doc = new DOMDocument();
			$doc->loadXML( $this->makeXML() );
			echo $doc->saveXML();
		}
	
		public function save($filename = 'sitemap.xml')
		{
			$file = fopen($filename, 'w') or die('file not create!');
			fwrite($file, $this->makeXML());
			fclose($file);
			echo $filename.' file successfully saved';
		}
		
	}

?>