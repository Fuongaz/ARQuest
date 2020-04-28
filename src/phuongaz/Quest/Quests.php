<?php

namespace phuongaz\Quest;

use phuongaz\Quest\Quest;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use jojoe77777\FormAPI\{SimpleForm, CustomForm, ModalForm};
class Quests
{
  	private $plugin;

	private $questCache = [];
	
	public function __construct(Quest $plugin){
        	$this->plugin = $plugin;
	}

	public function getPlugin() :Quest {
		return $this->plugin;
	}
	
	public function hasQuest(Player $player) : bool
	{
		$name = $player->getName();
		$result = $this->getPlugin()->db->query("SELECT * FROM pquests WHERE name= '$name';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}

	public function getPlayerQuest(Player $player) : ?string
	{
		$name = $player->getName();
		$result = $this->getPlugin()->db->query("SELECT * FROM pquests WHERE name = '$name';");
		$resultArr = $result->fetchArray(SQLITE3_ASSOC);
		return $resultArr["quest"];
	}
	
	public function validatePlayerQuest(Player $player, $quest) : bool
	{
		if($this->hasQuest($player) == false)
		{
			if($this->questExist($quest))
			{
					if($this->getPlugin()->hasSpace($player))
					{
						$this->givePlayerQuest($player, $quest);
						$player->sendMessage("§l§f•§a Bạn vừa nhận nhiệm vụ: §e". $this->getQuestTitle($quest));
						return true;
					}
					$player->sendMessage("§l§7Failed to insert Quest Book.");
					return false;
				
			}
			$player->sendMessage("§7§lAn error has occured, the quest may have been deleted on the process.");
			return false;
		}
		$player->sendMessage("§l§cBạn đang nhận 1 nhiệm vụ");
		return false;
	}

 	public function givePlayerQuest(Player $player, string $quest) : void
	{
		$stmt = $this->getPlugin()->db->prepare("INSERT OR REPLACE INTO pquests (name, quest) VALUES (:name, :quest);");
		$stmt->bindValue(":name", $player->getName());
		$stmt->bindValue(":quest", $quest);
		$result = $stmt->execute();
    }
	
	public function removePlayerQuest(Player $player) : void
	{
		$name = $player->getName();
		$this->getPlugin()->db->query("DELETE FROM pquests WHERE name = '$name';");
	}

	public function questExist(string $quest): bool
	{
		return (array_key_exists($quest, $this->getPlugin()->questData->getAll() )) ? true : false;
	}

	public function getQuestTitle(string $quest) : string
	{
		return $this->getPlugin()->questData->get($quest)['title'];
	}

	
	public function getQuestInfo(string $quest) : string
	{
		return $this->getPlugin()->questData->get($quest)['desc'];
	}

	public function getQuestItem(string $quest) : Item
	{
		$item = (string) $this->getPlugin()->questData->get($quest)['item'];
		$i = explode(":", $item);
		return Item::get($i[0], $i[1], $i[2]);
	}

	public function getQuestCmds(string $quest) : array
	{
		return $this->getPlugin()->questData->get($quest)['cmd'];
	}
	
	public function Completed(Player $player) : bool
	{
		if($player->getGamemode() <> 1){
			if( $this->hasQuest($player)){
				$quest = $this->getPlayerQuest($player);
				$item = $this->getQuestItem($quest);
				$hand = $player->getInventory()->getItemInHand();
				if($hand->getId() == $item->getId()){
					if($hand->getCount() >= $item->getCount()){
						$hand->setCount($hand->getCount() - $item->getCount());
						$player->getInventory()->setItemInHand($hand);
						foreach($this->getQuestCmds($quest) as $cmd){
							$this->getPlugin()->rca($player, $cmd);
						}
						$player->addTitle("§l§aComplete" , "§6". $this->getQuestTitle($quest));
						$this->removePlayerQuest($player);
						return true;
					}
					$player->sendMessage("§l• §aĐúng vật phẩm nhiệm vụ rồi, nhưng chưa đủ số lượng tìm thêm nhé :3.");
					return false;
				}
				$player->sendMessage("§l§l• §cBạn cần cầm vật phẩm nhiệm vụ.");
				return false;
			}
			$player->sendMessage("§l• §eBạn hiện chưa nhận nhiệm vụ nào.");
			return false;
		}
		$player->sendMessage("§l§7Creative mode is not allowed.");
		return false;
	}
	
	public function showQuest(Player $player) {
		$quest = $this->getPlayerQuest($player);
		$form = new SimpleForm(function(Player $player, $data) use ($quest){
			if(is_null($data)) return;
			if($this->hasQuest($player)){
				$dataq = $this->getPlugin()->questData->get($quest);
				if($data == 1){
					$this->removePlayerQuest($player);
					$player->sendMessage("§l§f•§c Đã hủy thành công nhiệm vụ đang làm");
					return;
				}
				$this->showInfo($player, $quest);
			}else{
				$this->sendQuestApplyForm($player);
			}
		});
		$form->setTitle("§l§6Nhiệm Vụ");
		if($this->hasQuest($player)){
			$form->addButton("§l§f•§0 ".$this->getQuestTitle($quest). " §f•");
			$form->addButton("§l§f•§0 Hủy nhiệm vụ đang làm §f•");
		}else{
			$form->addButton("§l§f•§c Bạn chưa nhận nhiệm vụ nào §f•");
		}
		$form->sendToPlayer($player);
	}

	public function showInfo(Player $player,string $quest){
		$form = new CustomForm(function(Player $player, ?array $data){
			if(is_null($data)) $this->getPlugin()->sendForm($player);
		});
		$title = $this->getQuestTitle($quest);
		$info = $this->getQuestInfo($quest);
		$form->setTitle("§l§6Nhiệm Vụ");
		$form->addLabel("§l".$info);
		$form->sendToPlayer($player);
	}

	
	public function sendQuestApplyForm(Player $player):void{
		$form = new SimpleForm(function(Player $player, ?int $data){
			if(is_null($data)) return;
				$button = $data;
				$list = array_keys( $this->getPlugin()->questData->getAll() );
				$quest = $list[ $button ];
				$this->questCache[ $player->getName() ] = $quest;
				$this->sendQuestInfo($player, $quest);
		});
        $form->setTitle("§l§6Nhiệm Vụ");
		if($this->hasQuest($player)){
			$form->setContent("§l§f•§e Bạn đang nhận nhiệm vụ hãy hoàn thành nó");
		}
		foreach( array_keys($this->getPlugin()->questData->getAll()) as $questid){
			$form->addButton("§l§f•§0 ".$this->getPlugin()->questData->getNested($questid.".title") ." §f•");
		}
        	$form->sendToPlayer($player);
    }
	
	public function sendQuestInfo(Player $player, string $quest) :void{
		$form = new ModalForm(function (Player $player,  $data){
			if(is_null($data)) $this->sendQuestApplyForm($player);
			if($data){
				$this->validatePlayerQuest($player,$this->questCache[$player->getName()]);
			}else $this->sendQuestApplyForm($player);
			if(array_key_exists($player->getName(), $this->questCache)){
				unset($this->questCache[$player->getName()]);
			}
		});
        $form->setTitle($this->getQuestTitle($quest));
		$form->setContent("§fNhiệm vụ:§a ". $this->getQuestTitle($quest). "\n§f-§6 ". $this->getQuestInfo($quest));
		$form->setButton1("§l§f•§0 Nhận§f •");
		$form->setButton2("§l§f•§0 Hủy§f •");
        $form->sendToPlayer($player);
	}
}