<?php
/**
 * 本插件于2018年5月6日开源
 * 禁止一切倒卖
 * 转载请务必注明出处
 * 作者: 数字
 * QQ: 1181334648
 * 如非必要将不再更新此插件
 * 此插件的售后服务于2018年5月7日起终止
 * Github: https://github.com/lakwsh/PM-AreaManager
 * 网站: https://lakwsh.net
 * 插件原名: AreaManage
 */
namespace AreaManager;
use pocketmine\Player;
use pocketmine\block\Air;
use pocketmine\level\Level;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\level\format\Chunk;
use pocketmine\utils\{Config,TextFormat};
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\command\{Command,CommandSender};

class Main extends \pocketmine\plugin\PluginBase implements \pocketmine\event\Listener{
	/** @var $server \pocketmine\Server */
	private static $server;
	private static $cfg;
	private static $msg;
	private static $chunk=array();
	private static $selecting=array();
	public function onLoad(){
		self::$server=parent::getServer();
		if(!defined('AMCP')) define('AMCP',parent::getDataFolder());
		if(!is_dir(AMCP)) mkdir(AMCP,0777,true);
		return;
	}
	public function onEnable(){
		self::$server->getPluginManager()->registerEvents($this,$this);
		self::getSetting();
		return;
	}
	private function GetLocation(Player $player,$v3=null){
		static $location=array();
		$msg=self::$msg;
		$name=self::getPlayerName($player);
		if($v3==null){
			if(!isset($location[$name])) return false;
			$all=$location[$name];
			unset($location[$name]);
			return $all;
		}elseif($v3 instanceof Vector3){
			$v3=array(round($v3->x),round($v3->y),round($v3->z));
			$level=$player->getLevel()->getName();
			if(!isset($location[$name])){
				$location[$name]['level']=$level;
				$location[$name]['start']=$v3;
			}else{
				if($location[$name]['level']!==$level){
					unset($location[$name]);
					$player->sendMessage($msg['Msg-NotSameWorld']);
					return false;
				}
				$location[$name]['end']=$v3;
			}
			$player->sendMessage(str_ireplace(array('&x&','&y&','&z&'),$v3,$msg['Msg-PosSave']));
			self::$selecting[$name]=false;
			return true;
		}
		return false;
	}
	private function SuperBlock(Player $player,string $mode,int $id=0,int $meta=0):bool{
		$time=microtime(true);
		$msg=self::$msg;
		$name=self::getPlayerName($player);
		if(!in_array($name,self::$cfg['Admin'])){
			$player->sendMessage($msg['Msg-NotPerm']);
			return false;
		}
		$path=AMCP.'AreaTmp.Data';
		if($mode=='pt' and !file_exists($path)){
			$player->sendMessage($msg['Msg-FileNotExist']);
			return false;
		}
		$all=self::GetLocation($player);
		if(!is_array($all)){
			$player->sendMessage($msg['Msg-PointNotChoose']);
			return false;
		}
		$pos1=$all['start'];
		$pos2=$all['end'];
		$sx=min($pos1[0],$pos2[0]);
		$ex=max($pos1[0],$pos2[0]);
		$sz=min($pos1[2],$pos2[2]);
		$ez=max($pos1[2],$pos2[2]);
		if($mode=='srm'){
			$sy=0;
			$ey=Level::Y_MASK;
		}else{
			$sy=min($pos1[1],$pos2[1]);
			$ey=max($pos1[1],$pos2[1]);
		}
		if($mode=='pt'){
			$data=explode(':',file_get_contents($path));
			if($data===false){
				$player->sendMessage($msg['Msg-WrongFile']);
				return false;
			}
			$total=$data[1];
			if(!is_numeric($total)){
				$player->sendMessage($msg['Msg-WrongFile']);
				return false;
			}
			unset($data);
		}else{$total=($ex-$sx+1)*($ey-$sy+1)*($ez-$sz+1);}
		$player->sendMessage(str_ireplace('&total&',$total,$msg['Msg-PointInfo']));
		$player->sendMessage(str_ireplace(array('&x&','&y&','&z&'),array($sx,$sy,$sz),$msg['Msg-StartPoint']));
		if($mode!='pt') $player->sendMessage(str_ireplace(array('&x&','&y&','&z&'),array($ex,$ey,$ez),$msg['Msg-EndPoint']));
		$level=self::$server->getLevelByName($all['level']);
		switch($mode){
			case 'set':
			case 'rm':
				if($mode=='set') $block=Block::get($id,$meta);
				else $block=new Air;
				for($z=$sz;$z<=$ez;$z++){
					for($x=$sx;$x<=$ex;$x++){
						for($y=$sy;$y<=$ey;$y++){
							self::setBlock($level,$x,$y,$z,$block);
						}
					}
				}
				break;
			case 'cp':
				$info=array($ex-$sx,$ey-$sy,$ez-$sz);
				$block=array();
				for($z=$sz;$z<=$ez;$z++){
					for($x=$sx;$x<=$ex;$x++){
						for($y=$sy;$y<=$ey;$y++){
							array_push($block,$level->getBlockIdAt($x,$y,$z).':'.$level->getBlockDataAt($x,$y,$z));
						}
					}
				}
				array_push($info,$block);
				unset($block);
				$info=json_encode($info);
				file_put_contents($path,sha1(sha1($info)).':'.$total.':'.base64_encode($info));
				return true;
			case 'pt':
				$data=explode(':',file_get_contents($path));
				$check=$data[0];
				$data=base64_decode($data[2]);
				if(sha1(sha1($data))!=$check){
					$player->sendMessage($msg['Msg-WrongFile']);
					return false;
				}
				$data=json_decode($data,true);
				$i=0;
				if($sx>$ex and $sz>$ez){
					//	x-- z--
					for($z=$sz-$data[2];$z<=$sz;$z++){
						for($x=$sx-$data[0];$x<=$sx;$x++){
							for($y=$sy;$y<=$sy+$data[1];$y++){
								$block=explode(':',$data[3][$i]);
								self::setBlock($level,$x,$y,$z,Block::get($block[0],$block[1]));
								++$i;
							}
						}
					}
				}elseif($sx>$ex and $sz<$ez){
					//	x-- z++
					for($z=$sz;$z<=$sz+$data[2];$z++){
						for($x=$sx-$data[0];$x<=$sx;$x++){
							for($y=$sy;$y<=$sy+$data[1];$y++){
								$block=explode(':',$data[3][$i]);
								self::setBlock($level,$x,$y,$z,Block::get($block[0],$block[1]));
								++$i;
							}
						}
					}
				}elseif($sx<$ex and $sz<$ez){
					//	x++ z++
					for($z=$sz;$z<=$sz+$data[2];$z++){
						for($x=$sx;$x<=$sx+$data[0];$x++){
							for($y=$sy;$y<=$sy+$data[1];$y++){
								$block=explode(':',$data[3][$i]);
								self::setBlock($level,$x,$y,$z,Block::get($block[0],$block[1]));
								++$i;
							}
						}
					}
				}elseif($sx<$ex and $sz>$ez){
					//	x++ z--
					for($z=$sz-$data[2];$z<=$sz;$z++){
						for($x=$sx;$x<=$sx+$data[0];$x++){
							for($y=$sy;$y<=$sy+$data[1];$y++){
								$block=explode(':',$data[3][$i]);
								self::setBlock($level,$x,$y,$z,Block::get($block[0],$block[1]));
								++$i;
							}
						}
					}
				}
				break;
			case 'srm':
				self::setFullChunk($level,$sx,$sz,$ex,$ez);
				break;
			default:
				return false;
		}
		self::saveBlock($level);
		$player->sendMessage(str_ireplace('&time&',substr(microtime(true)-$time,0,6),$msg['Msg-Done']));
		return true;
	}
	private function setBlock(Level $level,int $x,int $y,int $z,Block $block){
		$cx=$x>>4;
		$cz=$z>>4;
		$mix=$cx.'/'.$cz;
		if(!isset($chunk[$mix])) self::$chunk[$mix]=$level->getChunk($cx,$cz,true);
		self::$chunk[$mix]->setBlock($x&0x0f,$y&Level::Y_MASK,$z&0x0f,$block->getId(),$block->getDamage());
		return;
	}
	private function setFullChunk(Level $level,int $sx,int $sz,int $ex,int $ez){
		$list=array();
		$full=array();
		for($z=$sz;$z<=$ez;$z++){
			for($x=$sx;$x<=$ex;$x++){
				$mix=($x>>4).'/'.($z>>4);
				$point=$x.'/'.$z;
				if(!isset($full[$mix])) $full[$mix]=[$point];
				else $full[$mix][]=$point;
				if(isset($list[$mix])) ++$list[$mix];
				else $list[$mix]=1;
			}
		}
		foreach($list as $chunk=>$area){
			if($area!==0x100) continue;
			unset($full[$chunk]);
			$xz=explode('/',$chunk);
			$empty=new Chunk((int)$xz[0],(int)$xz[1]);
			$empty->setGenerated(true);
			$level->setChunk((int)$xz[0],(int)$xz[1],$empty,true);
		}
		$air=new Air;
		foreach($full as $chunk=>$points){
			foreach($points as $point){
				$xz=explode('/',$point);
				for($y=0;$y<Level::Y_MAX;$y++) self::setBlock($level,(int)$xz[0],$y,(int)$xz[1],$air);
			}
		}
		return;
	}
	private function saveBlock(Level $level){
		foreach(self::$chunk as $chunk) $level->setChunk($chunk->getX(),$chunk->getZ(),$chunk);
		self::$chunk=array();
		$level->saveChunks();
		return;
	}
	private function getPlayerName(Player $player){
		return strtolower(trim($player->getName()));
	}
	public function onCommand(CommandSender $sender,Command $cmd,string $label,array $args):bool{
		$msg=self::$msg;
		if(!($sender instanceof Player)){
			$sender->sendMessage(TextFormat::RED.'This command only can use in game.');
			return true;
		}
		/** @var $sender Player */
		$name=self::getPlayerName($sender);
		if(!isset($args[0])){
			$sender->sendMessage($msg['Msg-ChooseLoc']);
			self::$selecting[$name]=true;
			return true;
		}
		$action=strtolower($args[0]);
		switch($action){
			case 'set':
				$item=$sender->getInventory()->getItemInHand();
				$id=$item->getId();
				if($id>0xFF){
					$sender->sendMessage($msg['Msg-ErrBlock']);
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
		return true;
	}
	public function onBlockBreak(BlockBreakEvent $event){
		$player=$event->getPlayer();
		$name=self::getPlayerName($player);
		$v3=$event->getBlock();
		if(!isset(self::$selecting[$name])){
			self::$selecting[$name]=false;
		}elseif(self::$selecting[$name]){
			self::GetLocation($player,$v3);
			$event->setCancelled(true);
			return;
		}
	}
	public function onPlayerInteract(PlayerInteractEvent $event){
		$player=$event->getPlayer();
		$name=self::getPlayerName($player);
		$v3=$event->getBlock();
		if($v3->x==0 and $v3->y==0 and $v3->z==0) $v3=$event->getTouchVector();
		if(!isset(self::$selecting[$name])){
			self::$selecting[$name]=false;
		}elseif(self::$selecting[$name]!=false){
			self::GetLocation($player,$v3);
			$event->setCancelled(true);
			return;
		}
	}
	private function getSetting(){
		$data=array(
			'Admin'=>array('lakwsh','SuperAdmin'),
			'language'=>'chs'
		);
		if(!file_exists(AMCP.'Config.yml')){
			self::saveConfigFile($data);
			self::$cfg=$data;
		}else{
			$config=new Config(AMCP.'Config.yml',Config::YAML);
			$getData=$config->getAll();
			$check=self::checkConfig($data,$getData);
			if($check!=$getData) self::saveConfigFile($check);
			self::$cfg=$check;
		}
		self::getTranslate();
		return;
	}
	private function getTranslate(){
		new Config(AMCP.'lang_chs.yml',Config::DETECT,array(
			'Msg-NotSameWorld'=>'§c两点需在同一地图内! 已重置!',
			'Msg-Done'=>'§a完成.用时: &time&秒.',
			'Msg-PointNotChoose'=>'§c请确认已选择两个位置,已重置',
			'Msg-PointInfo'=>'§c共计处理&total&个方块',
			'Msg-StartPoint'=>'§a开始位置: x=&x& y=&y& z=&z&',
			'Msg-EndPoint'=>'§a结束位置: x=&x& y=&y& z=&z&',
			'Msg-NotPerm'=>'§c你无权使用此命令',
			'Msg-WorldNotLoad'=>'§c世界不存在或未加载',
			'Msg-ChooseLoc'=>'请选择位置',
			'Msg-PosSave'=>'已保存: x=&x& y=&y& z=&z&',
			'Msg-ErrBlock'=>'§c错误的方块id.',
			'Msg-FileNotExist'=>'§c请先复制一个区域后再调用此命令',
			'Msg-WrongFile'=>'§c区域数据损坏!无法执行粘贴命令!'
		));
		new Config(AMCP.'lang_eng.yml',Config::DETECT,array(
			'Msg-NotSameWorld'=>'§cPoint 1 must the same like Point 2 in the same Level!',
			'Msg-Done'=>'§aDone. time consuming: &time&S.',
			'Msg-PointNotChoose'=>'§cPlease confirm two points\'s position!',
			'Msg-PointInfo'=>'§cTotal deal with&total&blocks',
			'Msg-StartPoint'=>'§aStart position: x=&x& y=&y& z=&z&',
			'Msg-EndPoint'=>'§aEnd position: x=&x& y=&y& z=&z&',
			'Msg-NotPerm'=>'§cYou haven\'t permission to use this command!',
			'Msg-WorldNotLoad'=>'§cCannot find this Level or this Level doesn\'t enable.',
			'Msg-ChooseLoc'=>'Please choose a position.',
			'Msg-PosSave'=>'Saved successful: x=&x& y=&y& z=&z&',
			'Msg-ErrBlock'=>'§cincorrect block id.',
			'Msg-FileNotExist'=>'§cPlease choose an area and copy it, then you can to use this command!',
			'Msg-WrongFile'=>'§cArea Damage!Unable to execute paste command!'
		));
		$lang=self::$cfg['language'];
		if($lang!=='chs' and $lang!=='eng') $lang='eng';
		self::$msg=(new Config(AMCP.'lang_'.$lang.'.yml'))->getAll();
		return;
	}
	private function saveConfigFile(array $config){
		$data=new Config(AMCP.'Config.yml',Config::YAML);
		$data->setAll($config);
		$data->save();
		return;
	}
	private function checkConfig($ori,$check){
		foreach(array_keys($ori) as $key){
			if(isset($check[$key])){
				if(is_bool($ori[$key])){
					if(is_bool($check[$key])) $ori[$key]=$check[$key];
				}elseif(is_numeric($ori[$key])){
					if(is_numeric($check[$key])) $ori[$key]=abs($check[$key]);
				}elseif(is_array($ori[$key])){
					$ori[$key]=self::checkConfig($ori[$key],$check[$key]);
				}else{$ori[$key]=$check[$key];}
			}
		}
		return $ori;
	}
}
