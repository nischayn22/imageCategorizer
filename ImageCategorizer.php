<?php
/**
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @author Nischay Nahata for Aruba Networks as consultant for WikiWorks.com
 */
	$settings = array( 'wiki' => 'http://localhost/core' );
	$settings['user'] = "Nischayn22";
	$settings['pass'] = "password";
	$settings['imagesDirectory'] = "images";
	# Settings for Basic HTTP Auth
	$settings['serverAuth'] = true;
	$settings['AuthUsername'] = 'nischay';
	$settings['AuthPassword'] = 'password';

	// Leave this alone
	$settings['cookiefile'] = "cookies.tmp";

	echo "Logging in\n";
	try {
				global $settings;
				$token = login($settings['wiki'],$settings['user'], $settings['pass']);
				login($settings['wiki'],$settings['user'], $settings['pass'], $token);
				echo ("Successfully Logged In\n");
		} catch (Exception $e) {
				die("FAILED: " . $e->getMessage() . "\n");
		}
	$url = $settings['wiki'] . "/api.php?format=xml&action=query&titles=Main_Page&prop=info|revisions&intoken=edit";
	$data = httpRequest($url, $params = '');
	$xml = simplexml_load_string($data);
	$editToken = urlencode( (string)$xml->query->pages->page['edittoken'] );

	function login ( $site, $user, $pass, $token='') {

		$url = $site . "/api.php?action=login&format=xml";

		$params = "action=login&lgname=$user&lgpassword=$pass";
		if (!empty($token)) {
				$params .= "&lgtoken=$token";
		}

		$data = httpRequest($url, $params);
		
		if (empty($data)) {
				throw new Exception("No data received from server. Check that API is enabled.");
		}

		$xml = simplexml_load_string($data);
		if (!empty($token)) {
				//Check for successful login
				$expr = "/api/login[@result='Success']";
				$result = $xml->xpath($expr);

				if(!count($result)) {
						throw new Exception("Login failed");
				}
		} else {
				$expr = "/api/login[@token]";
				$result = $xml->xpath($expr);

				if(!count($result)) {
						throw new Exception("Login token not found in XML");
				}
		}
		
		return $result[0]->attributes()->token;
	}

	function httpRequest($url, $post="") {
		global $settings;

		$ch = curl_init();
		if( $settings['serverAuth'] ) {
			curl_setopt($ch, CURLOPT_USERPWD, $settings['AuthUsername'] . ":" . $settings['AuthPassword']);
		}
		//Change the user agent below suitably
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9');
		curl_setopt($ch, CURLOPT_URL, ($url));
		curl_setopt( $ch, CURLOPT_ENCODING, "UTF-8" );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt ($ch, CURLOPT_COOKIEFILE, $settings['cookiefile']);
		curl_setopt ($ch, CURLOPT_COOKIEJAR, $settings['cookiefile']);
		if (!empty($post)) curl_setopt($ch,CURLOPT_POSTFIELDS,$post);
		//UNCOMMENT TO DEBUG TO output.tmp
		//curl_setopt($ch, CURLOPT_VERBOSE, true); // Display communication with server
		//$fp = fopen("output.tmp", "w");
		//curl_setopt($ch, CURLOPT_STDERR, $fp); // Display communication with server
		
		$xml = curl_exec($ch);
		
		if (!$xml) {
				throw new Exception("Error getting data from server ($url): " . curl_error($ch));
		}

		curl_close($ch);
		
		return $xml;
	}
	function download($url, $file_target) {
		global $settings;
		$fp = fopen ( $file_target, 'w+');//This is the file where we save the information
		$ch = curl_init(str_replace(" ","%20",$url));//Here is the file we are downloading, replace spaces with %20
		if( $settings['serverAuth'] ) {
			curl_setopt($ch, CURLOPT_USERPWD, $settings['AuthUsername'] . ":" . $settings['AuthPassword']);
		}
		curl_setopt($ch, CURLOPT_URL, ($url));
	//	curl_setopt( $ch, CURLOPT_ENCODING, "UTF-8" );
	//	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $settings['cookiefile']);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $settings['cookiefile']);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, 50);
		curl_setopt($ch, CURLOPT_FILE, $fp); // write curl response to file
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		$xml = curl_exec($ch); // get curl response
		curl_close($ch);
		fclose($fp);
		return $xml;
	}

	function errorHandler( $xml ){
		if( property_exists( $xml, 'error' ) ) {
			$errors = is_array( $xml->error )? $xml->error : array( $xml->error );
			foreach( $errors as $error ) {
				echo "Error code: " . $error['code'] . " " . $error['info'] . "\n";
			}
		}
	}


	echo "Starting to download and categorize images\n";
	if ( !is_dir( $settings['imagesDirectory'] ) ) {
		// dir doesn't exist, make it
		echo "Creating directory " . $settings['imagesDirectory'] . "\n";
		mkdir( $settings['imagesDirectory'] );
	}
	chdir( $settings['imagesDirectory'] );
	// Initialize continue to be empty string
	$continue = '';
	do {
		$url = $settings['wiki'] . "/api.php?action=query&list=allpages&format=xml&apnamespace=6&aplimit=100&apfrom=$continue";
		$data = httpRequest($url, $params = '');
		$xml = simplexml_load_string($data);
		// var_dump($xml);
		$expr = "/api/query/allpages/p";
		$result = $xml->xpath($expr);
		foreach( $result as $page ) {
			$pageName = (string)$page['title'];
			$pageName = str_replace( ' ', '_', $pageName );
			// Get Namespace
			$parts = explode( ':', $pageName );
			$url = $settings['wiki'] . "/api.php?action=query&titles=$pageName&prop=imageinfo|categories&iiprop=url&format=xml";
			$data = httpRequest($url, $params = '');
			$xml = simplexml_load_string($data);
			$expr = "/api/query/pages/page/imageinfo/ii";
			$imageInfo = $xml->xpath($expr);
			$rawFileURL = (string) $imageInfo[0]['url'];

			$expr = "/api/query/pages/page/categories/cl";
			$categoryInfo = $xml->xpath($expr);
			if( $categoryInfo ) {
				$categoryName = (string)$categoryInfo[0]['title'];
				$categoryName = explode( ':', $categoryName );
				$categoryName = $categoryName[1];
//				echo getcwd();
				if ( !is_dir( $categoryName ) ) {
					// dir doesn't exist, make it
					echo "Creating directory " . $categoryName . "\n";
					mkdir( $categoryName );
				}
				echo "Downloading file " . $parts[1] . " \n";
				$result = download( $rawFileURL, $categoryName . "/" . $parts[1] );
			}
		}
		$expr = "/api/query-continue/allpages";
		$result = $xml->xpath($expr);
		if ( isset( $result[0]['apcontinue'] ) ) {
			$continue = (string)$result[0]['apcontinue'];
		} else {
			$continue = -1;
		}
	} while( $continue !== -1 );


	echo "Done\n";