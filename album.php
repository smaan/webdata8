<?php
//Sukhbir Singh - 1001081404
//http://omega.uta.edu/~sxs1404/project8/album.php
// display all errors on the browser
error_reporting(E_ALL);
ini_set('display_errors','On');
$uploadOk = 1;
$target_dir = "myfiles/";
if(isset($_POST["upload_btn"])) {
	//basename() function returns the filename from a path.
	$target_file = $target_dir.basename($_FILES["fileToUpload"]["name"]);
	$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
	// Check if file already exists
	if (file_exists($target_file)) {
		echo "Sorry, file already exists in directory.<br/>";
		$uploadOk = 0;
	}
	// Allow certain file formats
	elseif ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
		echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.<br/>";
		$uploadOk = 0;
	}
	// Check if $uploadOk is set to 0 by an error
	if ($uploadOk == 0) {
		echo "Your file was not uploaded!<br/>";
	// if everything is ok, try to upload file
	} else {
		if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
			echo "The file ". basename( $_FILES["fileToUpload"]["name"]). " has been uploaded.<br/><br/>";
			$uploadOk = 2;
		} else {
			echo "Sorry, there was an error uploading your file.<br/>";
		}
	}
}
set_time_limit(0);
require_once("DropboxClient.php");
$dropbox = new DropboxClient(array(
	'app_key' => "app_key",      // Put your Dropbox API key here
	'app_secret' => "app_secret",   // Put your Dropbox API secret here
	'app_full_access' => false,
	),'en');

function store_token($token, $name){
	if(!file_put_contents("tokens/$name.token", serialize($token)))
		die('<b>Could not store token! <b>Make sure that the directory `tokens` exists and is writable!</b>');
}

function load_token($name){
	if(!file_exists("tokens/$name.token")) return null;
	return @unserialize(@file_get_contents("tokens/$name.token"));
}

function delete_token($name){
	@unlink("tokens/$name.token");
}

// first try to load existing access token
$access_token = load_token("access");
if(!empty($access_token)) {
	$dropbox->SetAccessToken($access_token);
	//echo "loaded access token:";
	//print_r($access_token);
}
elseif(!empty($_GET['auth_callback'])) // are we coming from dropbox's auth page?
{
	// then load our previosly created request token
	$request_token = load_token($_GET['oauth_token']);
	if(empty($request_token)) die('Request token not found!');
	
	// get & store access token, the request token is not needed anymore
	$access_token = $dropbox->GetAccessToken($request_token);	
	store_token($access_token, "access");
	delete_token($_GET['oauth_token']);
}

// checks if access token is required
if(!$dropbox->IsAuthorized()){
	// redirect user to dropbox auth page
	$return_url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."?auth_callback=1";
	$auth_url = $dropbox->BuildAuthorizeUrl($return_url);
	$request_token = $dropbox->GetRequestToken();
	store_token($request_token, $request_token['t']);
	die("Authentication required. <a href='$auth_url'>Click here.</a>");
}

if($uploadOk == 2) {
	$dropbox->UploadFile($target_file);
 }

$files = $dropbox->GetFiles("",true);
?>
<html>
	<head>
		<title>Dropbox Images</title>
		<style type="text/css">
			.submitLink {
				background-color: transparent;
				text-decoration: underline;
				border: none;
				color: blue;
				cursor: pointer;
			}
			table, td, th{
				border: 1px solid black;
				text-align : left;
				
			}
			table {
				width: 50%;
				border-collapse: collapse;
			}
			
			table th {
				background-color: #333333;
				color: white;
				text-align: center;
			}
		</style>
	</head>
	<body>
		<div>
			<p>
				<b>
					<?php 
						if(isset($_GET['msg']) && $_GET['msg'] == 1)
							echo "Image Deleted!<br/>";
						else if(isset($_GET['msg']) && $_GET['msg'] == 0)
							echo "Error Deleting Image!<br/>";
					?>
				</b>
			</p>	
			<form action="album.php" method="POST" enctype="multipart/form-data">
				<table><tr><th><b>Select an Image to Upload</b></th></tr></table><br/>
				<input type="file" name="fileToUpload" id="fileToUpload">
				<input type="submit" value="Upload Image" name="upload_btn">
			</form>
		</div>
		<div>
			<?php
			if(!empty($files)) {?>
				<table>
				<tr><th colspan="3"><b>My Images</b></th></tr>
				<?php foreach ($files as $file){
					$img_name = substr($file->path, strpos($file->path, '/') + 1);
					/*code to download a file from dropbox to webserver
					$target_dir = "myfiles/";
					$test_file = $target_dir."local_".basename($file->path);
					$dropbox->DownloadFile($file, $test_file);
					$tf_name = "local_".$img_name;*/
				?>
					<form action="album.php" method="POST">
					<tr>
						<td><?php echo $img_name;?></td>
						<td><input type="submit" class="submitLink" value="View" name="view_btn"></td>
						<td><input type="submit" class="submitLink" value="Delete" name="delete_btn"></td>
						<input type="hidden" name="hiddenI" value="<?php echo $img_name?>">
						<input type="hidden" name="hiddenF" value="<?php echo $file->path?>">
					</tr>
					</form>
				<?php
				}
				echo"</table><br/><br/>";
			}?>
		</div>
		<div>
			<?php
			if(isset($_POST["view_btn"])) {
				if(isset($_POST['hiddenI'])){
					$image = $_POST['hiddenI'];
				}
				echo "<table><tr><th><b>Image Section</b></th></tr></table><br/>";
				echo "<img height='50%' width='50%' src='".$dropbox->GetLink($image,false)."'/><br/>";
			}
			elseif(isset($_POST["delete_btn"])) {
				if(isset($_POST['hiddenF'])){
					$fileD = $_POST['hiddenF'];
				}
				$dropbox->Delete($fileD);
				$delete_file = $target_dir.basename($fileD);
				if (!unlink($delete_file)){
					$message = 0;
				}
				else{
					$message = 1;
				}
				header("Location: album.php?msg=$message");
			}
			?>
		</div>
	</body>
</html>
