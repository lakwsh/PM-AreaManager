<?php
/**
 * 本插件于2018年5月6日开源
 * 禁止一切倒卖
 * 转载请注明出处,谢谢
 * 作者: 数字
 * QQ: 1181334648
 * 如非必要将不再更新此插件
 * 此插件的售后服务于2018年5月7日起终止
 */
namespace lakwsh\AreaManage;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\level\particle\DustParticle;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\MethodEventExecutor;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\EventPriority;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class AreaManage extends PluginBase implements Listener{
	//	插件初始化类
	//	变量定义
	private static $instance;
	private $lands=array();
	private $unlock=array();
	private $buymode=array();
	private $selectmode=array();
	private $saveloc=array();
	private $mny=array();
	private $WorldLoaded=false;
	private $cfg;
	private $wpath;
	private static $chunk=array('last'=>null);
	//	插件加载
	public function onLoad(){
		self::$instance=$this;
	}
	//	插件开启
	public function onEnable(){
		if(!defined('AMCP')) define('AMCP',$this->getDataFolder());
		if(!defined('AMCPL')) define('AMCPL',AMCP.'/lands/');
		if(!is_dir(AMCP)) mkdir(AMCP,0777,true);
		if(!is_dir(AMCPL)) mkdir(AMCPL,0777,true);
		$server=$this->getServer();
		$man=$server->getPluginManager();
		$man->registerEvent('pocketmine\\event\\player\\PlayerInteractEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onPlayerInteract'),$this,false);
		$man->registerEvent('pocketmine\\event\\block\\BlockPlaceEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onBlockPlace'),$this,false);
		$man->registerEvent('pocketmine\\event\\block\\BlockBreakEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onBlockBreak'),$this,false);
		$man->registerEvent('pocketmine\\event\\entity\\EntityDamageEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onEntityDamage'),$this,false);
		$man->registerEvent('pocketmine\\event\\player\\PlayerMoveEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onPlayerMove'),$this,false);
		$man->registerEvent('pocketmine\\event\\level\\LevelLoadEvent',$this,EventPriority::LOWEST,new MethodEventExecutor('onLevelLoad'),$this,false);
		self::getSetting();
		self::getLands();
		$this->wpath=$server->getDataPath().'/worlds/';
		$this->getLogger()->notice('插件初始化成功!感谢您购买本插件!如有问题请到售后群咨询,谢谢!');
		$this->getLogger()->notice('即将进行地图加载...');
	}
	//	插件接口
	public static function getInstance(){
		return self::$instance;
	}
	//	自定义函数
	//	获取点位置
	private function GetLocation(Block $block,Player $player){
		$cfg=$this->cfg;
		$name=self::SgetName($player);
		$this->unlock[$name]=false;
		$this->buymode[$name]=false;
		if(self::isProtectArea(array('object'=>$block,'check'=>true))!==false and !self::inarray($name,$cfg['SuperAdmin'])){
			$this->selectmode[$name]=false;
			$player->sendMessage($cfg['Msg-AreaAntiChoose']);
			return;
		}
		$mode=$this->selectmode[$name];
		$this->saveloc[$name][$mode]=new Vector3(round($block->x),round($block->y),round($block->z));
		if(!isset($this->saveloc[$name]['level'])){
			$this->saveloc[$name]['level']=$block->getLevel()->getId();
		}else{
			if($block->getLevel()->getId()!=$this->saveloc[$name]['level']){
				$player->sendMessage($cfg['Msg-NotSameWorld']);
				$this->saveloc[$name][1]=$this->saveloc[$name][2]=null;
				unset($this->saveloc[$name]['level']);
				return;
			}
		}
		$pos=$this->saveloc[$name][$mode];
		$player->sendMessage(str_ireplace(array('&mode&','&x&','&y&','&z&'),array($mode,$pos->getX(),$pos->getY(),$pos->getZ()),$cfg['Msg-EditPoint']));
		$this->selectmode[$name]=false;
		return;
	}
	//	方块操作
	private function SuperBlock(Player $player,$mode,$id=0,$meta=0){
		$stime=microtime(true);
		$cfg=$this->cfg;
		$server=$this->getServer();
		$name=self::SgetName($player);
		if(!isset($this->saveloc[$name]['level'])){
			$this->saveloc[$name][1]=$this->saveloc[$name][2]=null;
			$player->sendMessage($cfg['Msg-PointNotChoose']);
			return;
		}else{
			$pos1=$this->saveloc[$name][1];
			$pos2=$this->saveloc[$name][2];
			if($pos1==null||$pos2==null){
				$this->saveloc[$name][1]=$this->saveloc[$name][2]=null;
				unset($this->saveloc[$name]['level']);
				$player->sendMessage($cfg['Msg-PointNotChoose']);
				return;
			}
		}
		if($mode!='pt'){
			$satx=min($pos1->getX(),$pos2->getX());
			$endx=max($pos1->getX(),$pos2->getX());
			$saty=min($pos1->getY(),$pos2->getY());
			$endy=max($pos1->getY(),$pos2->getY());
			$satz=min($pos1->getZ(),$pos2->getZ());
			$endz=max($pos1->getZ(),$pos2->getZ());
			if($mode=='srm'){
				$saty=0;
				$endy=127;
			}
		}else{
			$satx=$pos1->getX();
			$endx=$pos2->getX();
			$saty=$pos1->getY();
			$satz=$pos1->getZ();
			$endz=$pos2->getZ();
			if($satx==$endx or $satz==$endz){
				$player->sendMessage($cfg['Msg-PointNotAllow']);
				return;
			}
		}
		$path=AMCP.'AreaTmp.Data';
		if($mode=='pt'){
			if(!file_exists($path)){
				$player->sendMessage($cfg['Msg-FileNotExist']);
				return;
			}
		}
		if($this->unlock[$name]==false){
			if($mode=='pt'){
				$data=explode(':',file_get_contents($path));
				$total=$data[1];
				unset($data);
				if(!is_numeric($total)){
					$player->sendMessage($cfg['Msg-WrongFile']);
					trigger_error('配置文件错误!请勿随意修改配置文件!',E_USER_WARNING);
					return;
				}
			}else{$total=($endx-$satx+1)*($endy-$saty+1)*($endz-$satz+1);}
			if(!self::inarray($name,$cfg['SuperAdmin'])) $player->sendMessage(str_ireplace('&total&',$total,$cfg['Msg-ReqAdminAcc']));
			foreach($server->getOnlinePlayers() as $p){
				if(self::inarray($name,$cfg['SuperAdmin'])){
					$p->sendMessage(str_ireplace(array('&name&','&total&'),array($name,$total),$cfg['Msg-ReqAcc']));
					$p->sendMessage(str_ireplace(array('&x&','&y&','&z&'),array($satx,$saty,$satz),$cfg['Msg-StartPoint']));
					if($mode!='pt') $p->sendMessage(str_ireplace(array('&x&','&y&','&z&'),array($endx,$endy,$endz),$cfg['Msg-EndPoint']));
					$p->sendMessage(str_ireplace(array('&name&','&mode&'),array($name,$mode),$cfg['Msg-UnlockHelp']));
					return;
				}
			}
			$player->sendMessage($cfg['Msg-AdminNotOnline']);
			return;
		}
		if($this->unlock[$name]!=$mode){
			$player->sendMessage($cfg['Msg-NotAuth']);
			$this->unlock[$name]=false;
			return;
		}
		$level=$server->getLevel($this->saveloc[$name]['level']);
		if($mode=='set'){
			$block=Block::get($id,$meta);
		}elseif($mode=='srm'||$mode=='rm'){
			$block=new Air;
		}
		if($mode!='cp' and $mode!='pt'){
			for($z=$satz;$z<=$endz;$z++){
				for($x=$satx;$x<=$endx;$x++){
					for($y=$saty;$y<=$endy;$y++){
						self::setBlock($level,$x,$y,$z,$block);
					}
				}
			}
		}elseif($mode=='pt'){
			$data=explode(':',file_get_contents($path));
			$checkdata=$data[0];
			$data=base64_decode($data[2]);
			if(sha1(sha1($data))!=$checkdata){
				unset($data);
				$player->sendMessage($cfg['Msg-WrongFile']);
				trigger_error('配置文件错误!请勿随意修改配置文件!',E_USER_WARNING);
				return;
			}
			$data=json_decode($data,true);
			$i=0;
			if($satx>$endx and $satz>$endz){
				//	x-- z--
				for($z=$satz-$data[2];$z<=$satz;$z++){
					for($x=$satx-$data[0];$x<=$satx;$x++){
						for($y=$saty;$y<=$saty+$data[1];$y++){
							$block=explode(':',$data[3][$i]);
							self::setBlock($level,$x,$y,$z,Block::get($block[0],$block[1]));
							++$i;
						}
					}
				}
			}elseif($satx>$endx and $satz<$endz){
				//	x-- z++
				for($z=$satz;$z<=$satz+$data[2];$z++){
					for($x=$satx-$data[0];$x<=$satx;$x++){
						for($y=$saty;$y<=$saty+$data[1];$y++){
							$block=explode(':',$data[3][$i]);
							self::setBlock($level,$x,$y,$z,Block::get($block[0],$block[1]));
							++$i;
						}
					}
				}
			}elseif($satx<$endx and $satz<$endz){
				//	x++ z++
				for($z=$satz;$z<=$satz+$data[2];$z++){
					for($x=$satx;$x<=$satx+$data[0];$x++){
						for($y=$saty;$y<=$saty+$data[1];$y++){
							$block=explode(':',$data[3][$i]);
							self::setBlock($level,$x,$y,$z,Block::get($block[0],$block[1]));
							++$i;
						}
					}
				}
			}elseif($satx<$endx and $satz>$endz){
				//	x++ z--
				for($z=$satz-$data[2];$z<=$satz;$z++){
					for($x=$satx;$x<=$satx+$data[0];$x++){
						for($y=$saty;$y<=$saty+$data[1];$y++){
							$block=explode(':',$data[3][$i]);
							self::setBlock($level,$x,$y,$z,Block::get($block[0],$block[1]));
							++$i;
						}
					}
				}
			}
			unset($data);
		}else{
			$areainfo=array($endx-$satx,$endy-$saty,$endz-$satz);
			$block=array();
			for($z=$satz;$z<=$endz;$z++){
				for($x=$satx;$x<=$endx;$x++){
					for($y=$saty;$y<=$endy;$y++){
						array_push($block,$level->getBlockIdAt($x,$y,$z).':'.$level->getBlockDataAt($x,$y,$z));
					}
				}
			}
			array_push($areainfo,$block);
			unset($block);
			$areainfo=json_encode($areainfo);
			file_put_contents($path,sha1(sha1($areainfo)).':'.($endx-$satx+1)*($endy-$saty+1)*($endz-$satz+1).':'.base64_encode($areainfo));
			unset($areainfo);
		}
		self::saveBlock($level);
		$this->unlock[$name]=false;
		$player->sendMessage(str_ireplace('&usetime&',substr(microtime(true)-$stime,0,6),$cfg['Msg-Done']));
		return;
	}
	private function setBlock(Level $level,$x,$y,$z,Block $block){
		$cx=$x>>4;
		$cz=$z>>4;
		$mix=$cx.'-'.$cz;
		if(self::$chunk['last']==null or !isset($chunk[$mix])){
			self::$chunk[$mix]=$level->getChunk($cx,$cz);
			self::$chunk['last']=$mix;
		}
		self::$chunk[$cx.'-'.$cz]->setBlock($x&0x0f,$y&Level::Y_MASK,$z&0x0f,$block->getId(),$block->getDamage());
	}
	private function saveBlock(Level $level){
		foreach(self::$chunk as $key=>$chunk){
			if($key=='last') continue;
			$level->setChunk($chunk->getX(),$chunk->getZ());
		}
		$level->saveChunks();
		self::$chunk=array('last'=>null);
	}
	//	是否保护区域
	public function isProtectArea($args=array()){
		$cfg=$this->cfg;
		if(!isset($args['object'])){
			trigger_error('关键参数获取失败!',E_USER_WARNING);
			return false;
		}
		foreach($cfg['ProtectArea'] as $area){
			$land=$this->lands[$area];
			if(strtolower($land['level'])!=bin2hex(strtolower(trim($args['object']->getLevel()->getFolderName())))) continue;
			if(isset($args['player'])){
				$name=self::SgetName($args['player']);
				if(self::inarray($name,$land['whitelist'])||self::inarray($name,$cfg['SuperAdmin'])) continue;
			}
			if(isset($land['start']) and isset($land['end'])){
				$s=$land['start'];
				$e=$land['end'];
				if(!is_array($s) or !is_array($e)){
					trigger_error('配置文件错误!请勿随意修改配置文件!',E_USER_WARNING);
					continue;
				}
				$startX=min($s[0],$e[0]);
				$endX=max($s[0],$e[0]);
				$startZ=min($s[1],$e[1]);
				$endZ=max($s[1],$e[1]);
				$x=$args['object']->x;
				$z=$args['object']->z;
				if(!($x>=$startX&&$x<=$endX&&$z>=$startZ&&$z<=$endZ)) continue;
			}
			if(isset($args['check'])){
				if($land['anti-cover']) return $area;
				else continue;
			}
			if(!isset($args['type'])) return $area;
			elseif($args['type']=='hurt' and !$land['canKill']) return $area;
			elseif($args['type']=='edit' and !$land['canEdit']) return $area;
			elseif($args['type']=='touch' and !$land['canTouch']) return $area;
			elseif($args['type']=='move' and !$land['canMove']) return $area;
			else continue;
		}
		return false;
	}
	//	确保所有名称相同
	private function SgetName($object){
		return strtolower(trim($object->getName()));
	}
	//	判断是否存在于数组
	private function inarray($find,$array){
		if(is_array($array) and isset($find)){
			$find=strtolower($find);
			foreach($array as $str){if(strtolower($str)==$find){return true;}}
		}
		return false;
	}
	//	钩子函数
	//	执行指令
	public function onCommand(CommandSender $sender,Command $cmd,$label,array $args){
		$cfg=$this->cfg;
		$server=$this->getServer();
		if($cmd=='lw'){
			$msg='世界列表: ';
			foreach(scandir($this->wpath,1) as $world){
				if($world!='.'&&$world!='..'&&is_dir($this->wpath.$world)){
					if(!$server->isLevelLoaded($world)) $msg.=TextFormat::GREEN.$world.',';
					else $msg.=TextFormat::RED.$world.',';
				}
			}
			$sender->sendMessage(substr($msg,0,-1));
			return true;
		}
		if(!($sender instanceof Player)){
			$sender->sendMessage(TextFormat::RED.'此命令只能在游戏中使用');
			return true;
		}
		if(!isset($args[0])) return false;
		$name=self::SgetName($sender);
		$action=strtolower($args[0]);
		$level=$sender->getLevel();
		if($cmd=='ul'){
			if(!isset($args[1])) return false;
			if(!self::inarray($name,$cfg['SuperAdmin'])){
				$sender->sendMessage($cfg['Msg-NotPerm']);
				return true;
			}
			foreach($server->getOnlinePlayers() as $p){
				$pname=self::SgetName($p);
				if($pname==$action){
					$this->unlock[$pname]=$args[1];
					$p->sendMessage($cfg['Msg-UnlockRedo']);
					if($p!=$sender) $sender->sendMessage($cfg['Msg-UnLock']);
					return true;
				}
			}
			$sender->sendMessage($cfg['Msg-PlayerNotFound']);
		}elseif($cmd=='w'){
			if($server->isLevelLoaded($action)){
				for($a=1;$a<=180;$a=$a+0.5) $level->addParticle(new DustParticle(new Vector3($sender->x,$sender->y+$a,$sender->z),0,255,255));
				$sender->teleport($server->getLevelByName($action)->getSpawnLocation());
			}else{$sender->sendMessage($cfg['Msg-WorldNotLoad']);}
		}elseif($cmd=='area'){
			switch($action){
				case '1':
				case '2':
					$sender->sendMessage(str_ireplace('&mode&',$action,$cfg['Msg-ChooseLoc']));
					$this->selectmode[$name]=$action;
					break;
				case 'set':
					$item=$sender->getItemInHand();
					$id=$item->getId();
					if($id>255){
						$sender->sendMessage($cfg['Msg-ErrBlock']);
						return true;
					}
					self::SuperBlock($sender,'set',$id,$item->getDamage());
					break;
				case 'cp':
					self::SuperBlock($sender,'cp');
					break;
				case 'pt':
					self::SuperBlock($sender,'pt');
					break;
				case 'srm':
					self::SuperBlock($sender,'srm');
					break;
				case 'rm':
					self::SuperBlock($sender,'rm');
					break;
				default:
					return false;
					break;
			}
		}elseif($cmd=='land'){
			if($args[0]=='s'){
				if($cfg['Land-Sell']){
					$cfg['Land-Sell']=false;
					$sender->sendMessage($cfg['Msg-SwitchOffSell']);
				}else{
					$cfg['Land-Sell']=true;
					$sender->sendMessage($cfg['Msg-SwitchOnSell']);
				}
				self::saveConfigFile('config',$cfg);
				return true;
			}
			if($args[0]=='l' and !isset($args[1])){
				$sender->sendMessage('受保护的区域列表: '.implode(', ',$cfg['ProtectArea']));
				return true;
			}
			if(!isset($args[1])) return false;
			$args[1]=strtolower($args[1]);
			if(strlen($args[1])<1){
				$sender->sendMessage($cfg['Msg-ErrAreaName']);
				return true;
			}
			if($action!='n'&&$action!='nw'&&$action!='b'){
				if(!self::inarray($args[1],$cfg['ProtectArea'])){
					$sender->sendMessage($cfg['Msg-AreaNotExist']);
					return true;
				}
				$tland=$this->lands[$args[1]];
			}
			switch($action){
				case 'n':
				case 'nw':
				case 'b':
					$wname=strtolower(trim($level->getFolderName()));
					if(self::inarray($args[1],$cfg['ProtectArea'])){
						$sender->sendMessage($cfg['Msg-AreaNameUsed']);
						break;
					}
					if($action=='nw'||$action=='n'){
						if(!$sender->isOp()){
							$sender->sendMessage($cfg['Msg-NotPerm']);
							break;
						}
					}
					if($action=='nw'){
						$data=array(
							'canEdit'=>false,
							'canTouch'=>true,
							'canKill'=>true,
							'canMove'=>true,
							'whitelist'=>array($name),
							'level'=>bin2hex($wname),
							'anti-cover'=>true
						);
					}else{
						if(!isset($this->saveloc[$name]['level'])){
							$this->saveloc[$name][1]=$this->saveloc[$name][2]=null;
							$sender->sendMessage($cfg['Msg-PointNotChoose']);
							break;
						}else{
							$savepos1=$this->saveloc[$name][1];
							$savepos2=$this->saveloc[$name][2];
							if($savepos1==null||$savepos2==null){
								$this->saveloc[$name][1]=$this->saveloc[$name][2]=null;
								unset($this->saveloc[$name]['level']);
								$sender->sendMessage($cfg['Msg-PointNotChoose']);
								break;
							}
						}
						if($action=='b'){
							if(!$cfg['Land-Sell']){
								$sender->sendMessage($cfg['Msg-ServerNotSwitchOn']);
								break;
							}
							$e=self::getPlugin('EconomyAPI');
							if($e==null){
								$this->getLogger()->warning('未找到经济核心(EconomyAPI),无法使用区域购买功能');
								$sender->sendMessage($cfg['Msg-ServerNotSwitchOn']);
								break;
							}
							if(!$this->buymode[$name]){
								$size1=abs($savepos1->getX()-$savepos2->getX());
								$size2=abs($savepos1->getZ()-$savepos2->getZ());
								$this->mny[$name]=round($size1*$size2*$cfg['per-square']/10);
								$sender->sendMessage(str_ireplace(array('&size-1&','&size-2&','&money&'),array($size1,$size2,$this->mny[$name]),$cfg['Msg-AreaBuy']));
								$sender->sendMessage($cfg['Msg-AreaBuyCheck']);
								$this->buymode[$name]=true;
								break;
							}else{
								$money=$e->myMoney($sender);
								if($this->mny[$name]>$money){
									$sender->sendMessage($cfg['Msg-NoMoney']);
									break;
								}else{
									$e->reduceMoney($sender,$this->mny[$name],true);
									$this->buymode[$name]=false;
								}
							}
						}
						$data=array(
							'canEdit'=>false,
							'canTouch'=>true,
							'canKill'=>true,
							'canMove'=>true,
							'whitelist'=>array($name),
							'start'=>array($savepos1->getX(),$savepos1->getZ()),
							'end'=>array($savepos2->getX(),$savepos2->getZ()),
							'level'=>bin2hex($wname),
							'anti-cover'=>true
						);
					}
					self::saveConfigFile($args[1],$data,false,true);
					$cfg['ProtectArea'][]=$args[1];
					self::saveConfigFile('config',$cfg,true,false,true);
					if($action!='b') $sender->sendMessage(str_ireplace('&name&',$args[1],$cfg['Msg-AreaCreated']));
					else $sender->sendMessage(str_ireplace('&name&',$args[1],$cfg['Msg-AreaBuySucc']));
					break;
				case 'd':
					if($tland['whitelist'][0]!=$name and !self::inarray($name,$cfg['SuperAdmin'])){
						$sender->sendMessage($cfg['Msg-NotYourArea']);
						break;
					}
					foreach($cfg['ProtectArea'] as $key=>$world){
						if($world==$args[1]){
							unset($cfg['ProtectArea'][$key]);
							break;
						}
					}
					self::saveConfigFile('config',$cfg,true,false,true);
					$sender->sendMessage(str_ireplace('&name&',$args[1],$cfg['Msg-AreaDeleted']));
					break;
				case 'f':
					if(!isset($args[2])) return false;
					if($tland['whitelist'][0]!=$name and !self::inarray($name,$cfg['SuperAdmin'])){
						$sender->sendMessage($cfg['Msg-NotYourArea']);
						break;
					}
					switch(strtolower($args[2])){
						case 'pvp':
							if($tland['canKill']){
								$this->lands[$args[1]]['canKill']=false;
								$sender->sendMessage($cfg['Msg-SwitchOnAntiPvP']);
							}else{
								$this->lands[$args[1]]['canKill']=true;
								$sender->sendMessage($cfg['Msg-SwitchOffAntiPvP']);
							}
							break;
						case 'edit':
							if($tland['canEdit']){
								$this->lands[$args[1]]['canEdit']=false;
								$sender->sendMessage($cfg['Msg-SwitchOnAntiEdit']);
							}else{
								$this->lands[$args[1]]['canEdit']=true;
								$sender->sendMessage($cfg['Msg-SwitchOffAntiEdit']);
							}
							break;
						case 'touch':
							if($tland['canTouch']){
								$this->lands[$args[1]]['canTouch']=false;
								$sender->sendMessage($cfg['Msg-SwitchOnAntiTouch']);
							}else{
								$this->lands[$args[1]]['canTouch']=true;
								$sender->sendMessage($cfg['Msg-SwitchOffAntiTouch']);
							}
							break;
						case 'move':
							if($tland['canMove']){
								$this->lands[$args[1]]['canMove']=false;
								$sender->sendMessage($cfg['Msg-SwitchOnAntiMove']);
							}else{
								$this->lands[$args[1]]['canMove']=true;
								$sender->sendMessage($cfg['Msg-SwitchOffAntiMove']);
							}
							break;
						default:
							$sender->sendMessage($cfg['Msg-EffectNotExist']);
							break;
					}
					self::saveConfigFile($args[1],$this->lands[$args[1]],false,true,true);
					break;
				case 'w':
					if(!isset($args[2])) return false;
					if($tland['whitelist'][0]!=$name and !self::inarray($name,$cfg['SuperAdmin'])){
						$sender->sendMessage($cfg['Msg-ChangeWhiteList']);
						break;
					}
					if(strtolower($args[2])==$name){
						$sender->sendMessage($cfg['Msg-DeleteWhiteFailed']);
						break;
					}
					if(self::inarray($args[2],$tland['whitelist'])){
						foreach($tland['whitelist'] as $key=>$pname){
							if(strtolower($pname)==$args[1]){
								unset($this->lands[$args[1]]['whitelist'][$key]);
								break;
							}
						}
						$sender->sendMessage(TextFormat::RED.'已删除'.$args[2]);
					}else{
						$this->lands[$args[1]]['whitelist'][]=$args[2];
						$sender->sendMessage(TextFormat::GREEN.'已添加'.$args[2]);
					}
					self::saveConfigFile($args[1],$this->lands[$args[1]],false,true,true);
					break;
				case 'g':
					if(!isset($args[2])) return false;
					if($tland['whitelist'][0]!=$name and !self::inarray($name,$cfg['SuperAdmin'])){
						$sender->sendMessage($cfg['Msg-ChangeWhiteList']);
						break;
					}
					if(strtolower($args[2])==$name){
						$sender->sendMessage($cfg['Msg-YourArea']);
						break;
					}
					$this->lands[$args[1]]['whitelist'][0]=$args[2];
					$sender->sendMessage(str_ireplace('&name&',$args[2],$cfg['Msg-AreaGiveSucc']));
					self::saveConfigFile($args[1],$this->lands[$args[1]],false,true,true);
					break;
				case 'a':
					if($tland['whitelist'][0]!=$name and !self::inarray($name,$cfg['SuperAdmin'])){
						$sender->sendMessage($cfg['Msg-NotYourArea']);
						break;
					}
					if($tland['anti-cover']){
						$this->lands[$args[1]]['anti-cover']=false;
						$sender->sendMessage($cfg['Msg-SwitchOffCover']);
					}else{
						$this->lands[$args[1]]['anti-cover']=true;
						$sender->sendMessage($cfg['Msg-SwitchOnCover']);
					}
					self::saveConfigFile($args[1],$this->lands[$args[1]],false,false,true);
					break;
				case 'l':
					$sender->sendMessage('区域'.$args[1].'的效果及白名单:');
					if($tland['canKill']) $m1='允许';
					else $m1='禁止';
					if($tland['canEdit']) $m2='允许';
					else $m2='禁止';
					if($tland['canTouch']) $m3='允许';
					else $m3='禁止';
					if($tland['canMove']) $m4='允许';
					else $m4='禁止';
					$sender->sendMessage('效果: PVP->'.$m1.' 编辑->'.$m2.' 触发机关->'.$m3.' 进入->'.$m4);
					$sender->sendMessage('白名单: '.implode(', ',$tland['whitelist']));
					break;
				default:
					return false;
					break;
			}
		}else{return false;}
		return true;
	}
	//	地图自动加载
	public function onLevelLoad($event){
		if(!$this->WorldLoaded){
			$this->WorldLoaded=true;
			$server=$this->getServer();
			foreach(scandir($this->wpath,1) as $dirfile){
				if($dirfile!='.'&&$dirfile!='..'&&is_dir($this->wpath.$dirfile)){
					if(!$server->isLevelLoaded($dirfile)){
						$this->getLogger()->info("加载世界 $dirfile 中...");
						$server->loadLevel($dirfile);
					}
				}
			}
			$this->getLogger()->notice('世界加载完毕.');
		}
	}
	//	玩家移动
	public function onPlayerMove($event){
		$cfg=$this->cfg;
		$player=$event->getPlayer();
		if(self::isProtectArea(array('object'=>$player,'type'=>'move','player'=>$player))!==false){
			$player->sendMessage($cfg['Msg-AreaAntiMove']);
			$event->setCancelled(true);
			return;
		}
	}
	//	实体被伤害
	public function onEntityDamage($event){
		$cfg=$this->cfg;
		$ent=$event->getEntity();
		if($ent instanceof Player){
			if(self::isProtectArea(array('object'=>$ent,'type'=>'hurt'))!==false){
				if($event instanceof EntityDamageByEntityEvent){
					$dam=$event->getDamager();
					if($dam instanceof Player) $dam->sendMessage($cfg['Msg-AreaAntiPvP']);
				}
				$event->setCancelled(true);
				return;
			}
		}
	}
	//	放置方块
	public function onBlockPlace($event){
		$cfg=$this->cfg;
		$player=$event->getPlayer();
		$name=self::SgetName($player);
		$block=$event->getBlock();
		if(!isset($this->selectmode[$name])){
			$this->selectmode[$name]=false;
		}elseif($this->selectmode[$name]!=false){
			self::GetLocation($block,$player);
			$event->setCancelled(true);
			return;
		}
		if(self::isProtectArea(array('object'=>$block,'type'=>'edit','player'=>$player))!==false){
			$player->sendMessage($cfg['Msg-AreaAntiEdit']);
			$event->setCancelled(true);
			return;
		}
	}
	//	破坏方块
	public function onBlockBreak($event){
		$cfg=$this->cfg;
		$player=$event->getPlayer();
		$name=self::SgetName($player);
		$block=$event->getBlock();
		if(!isset($this->selectmode[$name])){
			$this->selectmode[$name]=false;
		}elseif($this->selectmode[$name]!=false){
			self::GetLocation($block,$player);
			$event->setCancelled(true);
			return;
		}
		if(self::isProtectArea(array('object'=>$block,'type'=>'edit','player'=>$player))!==false){
			$player->sendMessage($cfg['Msg-AreaAntiEdit']);
			$event->setCancelled(true);
			return;
		}
	}
	//	点击
	public function onPlayerInteract($event){
		$cfg=$this->cfg;
		$player=$event->getPlayer();
		$name=self::SgetName($player);
		$block=$event->getBlock();
		if(!isset($this->selectmode[$name])){
			$this->selectmode[$name]=false;
		}elseif($this->selectmode[$name]!=false){
			self::GetLocation($block,$player);
			$event->setCancelled(true);
			return;
		}
		if(self::isProtectArea(array('object'=>$block,'type'=>'touch','player'=>$player))!==false){
			$player->sendMessage($cfg['Msg-AreaAntiTouch']);
			$event->setCancelled(true);
			return;
		}
	}
	//	插件全局参数类
	//	插件配置
	private function getSetting(){
		$data=array(
			'SuperAdmin'=>array('lakwsh','SuperAdmin'),
			'ProtectArea'=>array(),
			'per-square'=>100,
			'Land-Sell'=>true,
			'Msg-AreaAntiEdit'=>'§c保护区域,禁止编辑!',
			'Msg-AreaAntiPvP'=>'§c保护区域,禁止PVP!',
			'Msg-AreaAntiTouch'=>'§c保护区域,禁止触碰!',
			'Msg-AreaAntiChoose'=>'§c该点在保护区域内,操作已取消',
			'Msg-NotSameWorld'=>'§c两点需在同一地图内! 已重置!',
			'Msg-Done'=>'§a完成.用时: &usetime&秒.',
			'Msg-NotAuth'=>'§c您请求的权限与您将进行的操作不符',
			'Msg-PointNotChoose'=>'§c请确认已选择两个位置,已重置',
			'Msg-ReqAdminAcc'=>'§c共计处理&total&个方块,等待确认中.',
			'Msg-ReqAcc'=>'§c&name&请求处理&total&个方块',
			'Msg-StartPoint'=>'§a开始位置: x=&x& y=&y& z=&z&',
			'Msg-EndPoint'=>'§a结束位置: x=&x& y=&y& z=&z&',
			'Msg-UnlockHelp'=>'§a请输入 /ul &name& &mode& 继续',
			'Msg-AdminNotOnline'=>'§c此功能管理者不在线',
			'Msg-AreaAntiMove'=>'§c保护区域禁止进入',
			'Msg-EditPoint'=>'第&mode&个位置: &x& &y& &z&',
			'Msg-SwitchOnCover'=>'§a已启用禁止覆盖属性',
			'Msg-SwitchOffCover'=>'§c已取消禁止覆盖属性',
			'Msg-NotYourArea'=>'§c这片区域不属于你',
			'Msg-NotPerm'=>'§c你无权使用此命令',
			'Msg-UnLock'=>'§a已解锁',
			'Msg-UnlockRedo'=>'§a已解锁,请再次执行命令',
			'Msg-WorldNotLoad'=>'§c世界不存在或未加载.',
			'Msg-PlayerNotFound'=>'§c未找到该玩家',
			'Msg-ChooseLoc'=>'请选择第&mode&个位置',
			'Msg-ErrBlock'=>'§c错误的方块id.',
			'Msg-SwitchOffSell'=>'§c已关闭区域购买功能',
			'Msg-SwitchOnSell'=>'§a已开启区域购买功能',
			'Msg-ErrAreaName'=>'§c必须填写区域名',
			'Msg-AreaNotExist'=>'§c此区域不存在',
			'Msg-AreaNameUsed'=>'§c此区域名已被使用',
			'Msg-ErrLevel'=>'§c所选区域地图与您所处地图不匹配!',
			'Msg-ServerNotSwitchOn'=>'§c此服务器没有开启区域购买功能',
			'Msg-AreaBuy'=>'§c区域大小:&size-1&x&size-2&,所需金钱:&money&',
			'Msg-AreaBuyCheck'=>'§c请再次输入此命令确认购买',
			'Msg-NoMoney'=>'§c你没有足够的金钱购买此区域',
			'Msg-AreaCreated'=>'§a区域&name&创建成功',
			'Msg-AreaBuySucc'=>'§a区域&name&购买成功',
			'Msg-AreaDeleted'=>'§a区域&name&删除成功',
			'Msg-SwitchOffAntiPvP'=>'§c禁止PVP效果已禁用',
			'Msg-SwitchOnAntiPvP'=>'§a禁止PVP效果已启用',
			'Msg-SwitchOffAntiEdit'=>'§c禁止修改效果已禁用',
			'Msg-SwitchOnAntiEdit'=>'§a禁止修改效果已启用',
			'Msg-SwitchOffAntiTouch'=>'§c禁止触发效果已禁用',
			'Msg-SwitchOnAntiTouch'=>'§a禁止触发效果已启用',
			'Msg-SwitchOffAntiMove'=>'§c禁止进入效果已禁用',
			'Msg-SwitchOnAntiMove'=>'§a禁止进入效果已启用',
			'Msg-EffectNotExist'=>'§c没有此效果',
			'Msg-DeleteWhiteFailed'=>'§c你是这片区域的创建者,无法删除',
			'Msg-YourArea'=>'§c你是这片区域本来就属于你',
			'Msg-AreaGiveSucc'=>'§a成功转让此区域给&name&',
			'Msg-FileNotExist'=>'§c请先复制一个区域后再调用此命令',
			'Msg-WrongFile'=>'§c区域数据损坏!无法执行粘贴命令!',
			'Msg-PointNotAllow'=>'§c请选择两个不同位置的点以作粘贴方向'
		);
		$getdata=self::getConfigFile('config');
		if($getdata==null){
			self::saveConfigFile('config',$data,false);
			$this->getLogger()->notice('请注意修改配置文件中的SuperAdmin项!');
			$this->cfg=$data;
		}else{
			$checkdata=self::checkConfig($data,$getdata);
			if($checkdata!=$getdata) self::saveConfigFile('config',$checkdata,false);
			$this->cfg=$checkdata;
		}
		return;
	}
	//	区域设置
	private function getLands(){
		$area=$this->cfg['ProtectArea'];
		if(count($area)<1){
			$this->lands=null;
			return;
		}
		$data=array(
			'canEdit'=>false,
			'canTouch'=>true,
			'canKill'=>true,
			'canMove'=>true,
			'whitelist'=>array(),
			'anti-cover'=>true
		);
		foreach($area as $key=>$land){
			$lands[$land]=self::getConfigFile($land,true);
			if(!isset($lands[$land]['level'])){
				unset($this->cfg['ProtectArea'][$key]);
				self::saveConfigFile('config',$this->cfg);
				unset($lands[$land]);
				continue;
			}
			foreach(array_keys($data) as $key){
				if(!isset($lands[$land][$key])){
					$lands[$land][$key]=$data[$key];
				}elseif(!is_bool($lands[$land][$key]) and $key!='whitelist'){
					$lands[$land][$key]=$data[$key];
				}
			}
			self::saveConfigFile($land,$lands[$land],false,true);
		}
		$this->lands=$lands;
		return;
	}
	//	配置文件类
	//	配置文件->读取
	private function getConfigFile($filename,$island=false){
		$filename=strtolower(urlencode(trim($filename)));
		if($island) $path=AMCPL.$filename.'.yml';
		else $path=AMCP.$filename.'.yml';
		if(!file_exists($path)){
			return null;
		}else{
			$config=new Config($path,Config::YAML);
			return $config->getAll();
		}
	}
	//	配置文件->写入
	private function saveConfigFile($filename,array $config,$update=true,$island=false,$landupdate=false){
		$filename=strtolower(urlencode(trim($filename)));
		if($island) $path=AMCPL.$filename.'.yml';
		else $path=AMCP.$filename.'.yml';
		$data=new Config($path,Config::YAML);
		$data->setAll($config);
		$data->save();
		if($update) self::getSetting();
		if($landupdate) self::getLands();
		return;
	}
	//	配置文件格式检测
	private function checkConfig($ori,$check){
		foreach(array_keys($ori) as $key){
			if(isset($check[$key])){
				if(is_bool($ori[$key])){
					if(is_bool($check[$key])) $ori[$key]=$check[$key];
				}elseif(is_numeric($ori[$key])){
					if(is_numeric($check[$key])) $ori[$key]=abs($check[$key]);
				}elseif(is_array($ori[$key])){
					if(is_array($check[$key])) $ori[$key]=$check[$key];
				}else{$ori[$key]=$check[$key];}
			}
		}
		if($ori!==$check) $this->getLogger()->emergency('配置文件中部分配置项错误!已恢复为默认值,请注意.');
		return $ori;
	}
	//	接口类
	//	插件调用
	private function getPlugin($name){
		$man=$this->getServer()->getPluginManager();
		$plu=$man->getPlugin($name);
		if($plu!=null) if($man->isPluginEnabled($plu)){return $plu;}
		else return null;
	}
}