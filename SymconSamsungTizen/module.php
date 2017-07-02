<?
    include("websocket_client.php");
    
    // Klassendefinition
    class SamsungTizen extends IPSModule {
        public function __construct($InstanceID) {
            parent::__construct($InstanceID);
        }

        public function Create() {
            parent::Create();

            // Modul-Eigenschaftserstellung
            $this->RegisterPropertyString("IPAddress", "192.168.178.1"); 
            $this->RegisterPropertyString("MACAddress", "aa:bb:cc:00:11:22"); 
            $this->RegisterPropertyInteger("Timeout", 3);
            $this->RegisterPropertyInteger("Interval", 10);

            //event erstellen
            $this->RegisterTimer("CheckOnline", $this->ReadPropertyInteger("Interval"), 'CheckOnline_SyncData($_IPS[\'TARGET\'], false);');
            $this->SetStatus(102);
        }

        public function ApplyChanges() {
            // Diese Zeile nicht löschen
            parent::ApplyChanges();

            $this->SetStatus(102);
            $this->SetTimerInterval("CheckOnline", $this->ReadPropertyInteger("Interval")*1000);
        }

        public function WakeUp(){
            $broadcast = $this->ReadPropertyString("IPAddress");
            $mac_addr = $this->ReadPropertyString("MACAddress");
            $timeout = $this->ReadPropertyInteger("Timeout");

            if (!$fp = fsockopen('udp://' . $broadcast, 2304, $errno, $errstr, $timeout)) 
                return false; 

            $mac_hex = preg_replace('=[^a-f0-9]=i', '', $mac_addr); 

            $mac_bin = pack('H12', $mac_hex); 

            $data = str_repeat("\xFF", 6) . str_repeat($mac_bin, 16); 

            fputs($fp, $data); 
            fclose($fp); 
            return true; 

        }

        public function SendKey(string $key = null){
            $broadcast = $this->ReadPropertyString("IPAddress");
            $timeout = $this->ReadPropertyInteger("Timeout");
            $headers = ["Cookie: SID=".session_id()];
            $url = $broadcast . "/api/v2/channels/samsung.remote.control";

            $sp = websocket_open($url,8001,$headers,$errstr,$timeout);
            if($sp){
                if(is_null($key)) return true;
                $bytes_written = websocket_write($sp,"hello server");
                if($bytes_written){
                    $data = websocket_read($sp,$errstr);
                    echo "Server responed with: " . $errstr ? $errstr : $data;
                    $this->SetStatus(102);
                    return true;
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }

        public function CheckOnline(){
            $Intid = $this->InstanceID;
            if(@IPS_GetVariableIDByName("Online", $Intid) === False){
                $VarID = IPS_CreateVariable(0);
                IPS_SetName($VarID, "Online"); // Variable benennen
                IPS_SetParent($VarID, $Intid);
                IPS_SetVariableCustomProfile($VarID, "~Switch");
            }
            $VarID = @IPS_GetVariableIDByName("Online", $Intid);
            if($VarID !== False){
                SetValueBoolean($VarID, SamsungTizen_SendKey(null));
            }
        }
    }
?>