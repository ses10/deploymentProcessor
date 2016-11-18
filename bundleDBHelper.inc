<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');


class DatabaseHelper
{
	private $conn;

	public function connect()
	{
		try
		{
			$this->conn = new PDO('mysql:host=localhost;dbname=bundles', 'root', 'qwerty');
			return true;
		}
		catch(PDOException $e)
		{
			$message =  'Error: ' . $e->getMessage();
			echo $message;
		}
		
		return false;
	}

	//returns the next version of a bundle given its name
	//but does not update row
	public function getNextVersion($bundle)
	{
		$stmt = $this->conn->prepare('SELECT currentVer FROM bundle WHERE bundleName = :name');
		$stmt->bindParam(':name', $bundle);
		$stmt->setFetchMode(PDO::FETCH_ASSOC);
		$stmt->execute();
		
		$ver = $stmt->fetch()['currentVer'];

		return $ver + 1;
	}

	public function updateVersion($bundle)
	{
		$nextVer = $this->getNextVersion($bundle);

		$stmt = $this->conn->prepare("UPDATE bundle SET currentVer = :nextVer WHERE bundleName = :name");
		$stmt->bindParam(':nextVer', $nextVer);
		$stmt->bindParam(':name', $bundle);
		$stmt->execute();

		if( $stmt->rowCount() > 0)
			return true;
		return false;
	}
	
}

?>	
