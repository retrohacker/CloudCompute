<?php
/**
 * The Server class handles receiving connections, managing communication
 * groups, creating nodes, etc.
 */
class Server {
	//The address of the server
	private $address;

	//The port the server is connecting to
	private $port;
	
	//The socket for the main connection
	private $masterSocket;

	/**
	 * These 2 queues and 1 array are used to keep track of what "state"
	 * sockets are in within the program.
	 * 
	 * unallocated sockets: Sockets that are have connected to the server
	 * 	but have yet to be assigned to a node
	 * allocated sockets: Sockets that have connected to the server and
	 * 	have been assigned to a node. This is an array.
	 * disconnected sockets: Sockets that have disconnected from the server
	 * 	but are still assigned to nodes.
	 *
	 * When a client first connects, their socket is added to the
	 * unnallocatedSockets queue. Once the program assigns the socket
	 * to a node, its socket gets moved to allocatedSockets array. Then
	 * when a client disconnects from the server, it's socket gets moved
	 * from which ever queue it belongs to based on the above criteria to
	 * the disconnectedSocket. It stays in the disconnectedSocket queue
	 * until the node it has been assigned to has been deleted.
	 */
	private $unallocatedSockets = array();
	private $allocatedSockets = array();
	private $disconnectedSockets = array();

	//This array stores all the active nodes
	private $nodeArray = array();

	//This array stores all of the communication groups being used by the
	//server to communicate with multiple nodes at the same time
	private $commGroupsArray = array();

	/**
	 * Requests are instructions to the server to carry out a specific
	 * function such as send a message, add a node to a group, etc.
	 * */
	//Requests that have been received by the server but not yet processed
	private $pendingRequests = array();
	
	//Requests that have been processed by the server. They remain here
	//until the function/project that opened the request checks to see if
	//it has been completed
	private $completedRequests = array();

	public function __construct($address, $port) {
		$this->address = $address;
		$this->port = $port;

		//Create the main socket that the server will use
		$this->masterSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_nonblock($this->masterSocket);
		socket_set_option($this->masterSocket, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_bind($this->masterSocket, $this->address, $this->port);
		socket_listen($this->masterSocket, SOMAXCONN);
		//We prevent the master socket from blocking so when we poll it
		//in the future we don't have to worry about the program
		//becomming unresponsive
		socket_set_nonblock($this->masterSocket);

		echo "Server started on {$this->address}:{$this->port}\n";
	}

	/**
	 * Temporarily adds a newly connected socket to the queue of
	 * unallocated sockets until it is converted to a node.
	 */
	private function connect($socket) {
		//Pretty sure we can make a more efficent queue. Possibly
		//in a later version of the product
		array_unshift($this->unallocatedSockets, $socket);
	}

	/**
	 * Upgrades a simple socket connection to a node class
	 */
	private function createNode($socket) {
		$newNode = new Node($socket);
		//Checks to see if the handshake was successful
		if($newNode->getHandshake()) {
			$this->nodesArray[] = $newNode;
			echo "Created Node With ID: ".$newNode->getID()."\n";
			return true;
		}
		//If not we disconnect the socket
		else
		{
			echo "Failed to create node. Closing Socket\n";
			socket_close($socket);
			return false;
		}
	}

	/**
	 * Will take up to n sockets from the unallocatedNodes queue 
	 * and upgrade them to Nodes
	 */
	private function upgradeSockets($n) {
		//We check to see if there are less unallocated sockets then
		//what was requested to be upgraded
		$totalUnallocated = count($this->unallocatedSockets);
		if($n > $totalUnallocated) {
			//if so we simply upgrade the entire array
			$n = $totalUnallocated;
		}

		for($i=0;$i<$n;$i++) {
			//Remove the socket from the unallocatedSockets queue
			$socket = array_pop($this->unallocatedSockets);
			//Attempt to create a node
			$success = $this->createNode($socket);
			//Add the socket to the allocatedSockets array if
			//successful
			if($success)
				$this->allocatedSockets[] = $socket;
		}
	}

	/**
	 * Move an active socket into the disconnectedSockets queue
	 * returns true if successful, false otherwise
	 */
	private function disconnect($socket) {
		echo "test\n";
		//Search for the socket in the queues searching the one with
		//the least number of clients first
		$unallocatedKey = array_search($socket, $this->unallocatedSockets);
		$allocatedKey;
		//If the socket is not in the unallocated array, search the allocated
		if($unallocatedKey===false) 
			$allocatedKey = array_search($socket, $this->allocatedSockets);
		//If the socket is not in either array, it is not known to the server
		//and can not be disconnected thus this function failed
		if($unallocatedKey===false&&$allocatedKey===false) {
			echo "Did not find socket!\n";
			return false;
		}
		//Remove the socket from the array it was found in
		/**
		 * TODO: Check the length of the returned array from array_splice
		 * to ensure it is 1. If it is not we should report an error and
		 * crash.
		 */
		if($unallocatedKey!==false)
			array_splice($this->unallocatedSockets,$unallocatedKey,1);
		else if($allocatedKey!==false)
			array_splice($this->allocatedSockets,$allocatedKey,1);
		else
			return false;
		//TODO ensure size of array has increased by 1 otherwise crash
		array_unshift($this->disconnectedSockets,$socket);
		echo "Disconnected Socket";
	}

	/**
	 * Deletes up to n nodes whos sockets are in the disconnectedSockets
	 * queue
	 */
	private function deactivateNode($n) {
		for($i=0;$i<$n;$i++) {
			$deletedNode = array_pop($this->disconnectedSockets);
			if($deletedNode!=null)
				echo "Disconnected {$deletedNode->getID()}";
		}
	}

	/**
	 * Finds the node assigned to a socket. If no node is using the socket
	 * this function returns false
	 */
	private function getNodeBySocket($socket) {
		foreach($this->nodeArray as $node) {
			if($node->getSocket() == $socket)
				return $node;
		}
		return false;
	}

	/**
	 * Finds and returns a node by its ID. If no node has the specified ID
	 * this function will return false
	 */
	private function getNodeByID($id) {
		foreach($this->nodeArray as $node) {
			if($node->getID() == $id)
				return $node;
		}
		return false;
	}

	public function send($id, $text) {
		//TODO write function
	}

	/**
	 * This function checks all of the sockets connected to the server to
	 * see if there is any data waiting to be received, if so it opens up
	 * a request token for the node that received it.
	 */
	public function receiveData() {
		$changedSockets = $this->allocatedSockets;
		//Checks if there is any new data returning immediately
		@socket_select($changedSockets, $write = null, $except = null, 0);
		//If any data was received process it
		foreach($changedSockets as $socket) {
			$node = $this->getNodeBySocket($socket);
			if($node) {
				$bytes = socket_recv($socket, $data, 2048, 0);
				echo "received: ".$bytes."\n";
			}
		}
	}

	/**
	 * Polls the Master Socket to receive any new connections. If there are
	 * any new connections we add them to the unallocated clients list
	 */
	public function getNewConnections() {
		while(($newConnection = @socket_accept($this->masterSocket)) !== false) {
			echo "Connected: ".$newConnection."\n";
			$this->connect($newConnection);
		}
	}

	public function run() {
		while(true) {
			$this->receiveData();
			$this->getNewConnections();
			$this->upgradeSockets(1);
			$this->deactivateNode(1);
		}
	}
}
?>
