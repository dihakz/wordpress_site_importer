<?php  
defined('C5_EXECUTE') or die(_("Access Denied."));
//this is just a cobbling together of the file_importer and the file_incoming
class WordpressFileImporter {

	function importFile($fileUrl){
	$u = new User();
	
	$cf = Loader::helper('file');
	$fp = FilePermissions::getGlobal();
	if (!$fp->canAddFiles()) {
		die(t("Unable to add files."));
	}
	
	//$valt = Loader::helper('validation/token');
	Loader::library("file/importer");
	Loader::library('3rdparty/Zend/Http/Client');
	Loader::library('3rdparty/Zend/Uri/Http');
	$file = Loader::helper('file');
	Loader::helper('mime');
	
	$error = array();
	
	// load all the incoming fields into an array
	$this_url = $fileUrl;
	
		// validate URL
		if (Zend_Uri_Http::check($this_url)) {
			// URL appears to be good... add it
			$incoming_urls[] = $this_url;
		} else {
			$errors[] = '"' . $this_url . '"' . t(' is not a valid URL.');
		}
	//}
	
	//if (!$valt->validate('import_remote')) {
	//	$errors[] = $valt->getErrorMessage();
	//}
	
	
	if (count($incoming_urls) < 1) {
		$errors[] = t('You must specify at least one valid URL.');
	}
	
	$import_responses = array();
	
	// if we haven't gotten any errors yet then try to process the form
	if (count($errors) < 1) {
		// itterate over each incoming URL adding if relevant
		foreach($incoming_urls as $this_url) {
			// try to D/L the provided file
			$client = new Zend_Http_Client($this_url);
			$response = $client->request();
	
			if ($response->isSuccessful()) {
				$uri = Zend_Uri_Http::fromString($this_url);
				$fname = '';
				$fpath = $file->getTemporaryDirectory();
	
				// figure out a filename based on filename, mimetype, ???
				if (preg_match('/^.+?[\\/]([-\w%]+\.[-\w%]+)$/', $uri->getPath(), $matches)) {
					// got a filename (with extension)... use it
					$fname = $matches[1];
				} else if (! is_null($response->getHeader('Content-Type'))) {
					// use mimetype from http response
					$fextension = MimeHelper::mimeToExtension($response->getHeader('Content-Type'));
					if ($fextension === false)
						$errors[] = t('Unknown mime-type: ') . $response->getHeader('Content-Type');
					else {
						// make sure we're coming up with a unique filename
						do {
							// make up a filename based on the current date/time, a random int, and the extension from the mime-type
							$fname = date('d-m-Y_H:i_') . mt_rand(100, 999) . '.' . $fextension;
						} while (file_exists($fpath.'/'.$fname));
					}
				} //else {
					// if we can't get the filename from the file itself OR from the mime-type I'm not sure there's much else we can do
				//}
	
				if (strlen($fname)) {
					// write the downloaded file to a temporary location on disk
					$handle = fopen($fpath.'/'.$fname, "w");
					fwrite($handle, $response->getBody());
					fclose($handle);
	
					// import the file into concrete
					if ($fp->canAddFileType($cf->getExtension($fname))) {
						$fi = new FileImporter();
						$resp = $fi->import($fpath.'/'.$fname, $fname, $fr);
					} else {
						$resp = FileImporter::E_FILE_INVALID_EXTENSION;
					}
					if (!($resp instanceof FileVersion)) {
						$errors[] .= $fname . ': ' . FileImporter::getErrorMessage($resp) . "\n";
					} else {
						$import_responses[] = $resp;
					}
	
					// clean up the file
					unlink($fpath.'/'.$fname);
				} else {
					// could not figure out a file name
					$errors[] = t('Could not determine the name of the file at ') . $this_url;
				}
			} else {
				// warn that we couldn't download the file
				$errors[] = t('There was an error downloading ') . $this_url;
			}
		}
	}
	//print_r($errors);
	if($resp instanceof FileVersion){
		return $resp;
	}
	
	}
} //end class
?>
