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
	/** @var Quest */
  	private $plugin;
	
	private $questCache = [];
	
	public function __construct(Quest $plugin){
        	$this->plugin = $plugin;
	}

	/**
	* @return Quest
	*/
	public function getPlugin() :Quest {
		return $this->plugin;
	}
	
	/**
	* @param Player $player
	* @return bool
	*/
	public function hasQuest(Player $player) : bool
	{
		$name = $player->getName();
		$result = $this->getPlugin()->db->query("SELECT * FROM pquests WHERE name= '$name';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}

	/**
	* @param Player $player
	* @return string|null
	*/
	public function getPlayerQuest(Player $player) : ?string
	{
		$name = $player->getName();
		$result = $this->getPlugin()->db->query("SELECT * FROM pquests WHERE name = '$name';");
		$resultArr = $result->fetchArray(SQLITE3_ASSOC);
		return $resultArr["quest"];
	}
	
	/**
	* @param Player $player
	* @param string|array|null $quest
	*/
	public function validatePlayerQuest(Player $player, $quest) : bool
	{
		if($this->hasQuest($player) == false)
		{
			if($this->questExist($quest))
			{
				$this->givePlayerQuest($player, $quest);
				$player->sendMessage($this->getPlugin()->getConfig()->get('Take-quest'). $this->getQuestTitle($quest));
				return true;
				
			}
			$player->sendMessage("§7§lAn error has occured, the quest may have been deleted on the process.");
			return false;
		}
		$player->sendMessage($this->getPlugin()->getConfig()->get('Have-quest'));
		return false;
	}

	/**
	* @param Player $player
	* @param string $quest
	*/
 	public function givePlayerQuest(Player $player, string $quest)
	{
		$stmt = $this->getPlugin()->db->prepare("INSERT OR REPLACE INTO pquests (name, quest) VALUES (:name, :quest);");
		$stmt->bindValue(":name", $player->getName());
		$stmt->bindValue(":quest", $quest);
		$result = $stmt->execute();
    	}

	/**
	* @param Player $player
	*/	
	public function removePlayerQuest(Player $player)
	{
		$name = $player->getName();
		$this->getPlugin()->db->query("DELETE FROM pquests WHERE name = '$name';");
	}

	/**
	* @param string $quest
	* @return bool
	*/
	public function questExist(string $quest): bool
	{
		return (array_key_exists($quest, $this->getPlugin()->questData->getAll() )) ? true : false;
	}

	/**
	* @param string $quest
	* @return string
	*/
	public function getQuestTitle(string $quest) : string
	{
		return $this->getPlugin()->questData->get($quest)['title'];
	}

	/**
	* @param string $quest
	* @return string
	*/
	public function getQuestInfo(string $quest) : string
	{
		return $this->getPlugin()->questData->get($quest)['desc'];
	}



	/**
	* @param string $quest
	* @return Item
	*/
	public function getQuestItem(string $quest) : Item
	{
		$item = (string) $this->getPlugin()->questData->get($quest)['item'];
		$i = explode(":", $item);
		return Item::get((int)$i[0], (int)$i[1], (int)$i[2]);
	}



	/**
	* @param string $quest
	* @return array[] Commands
	*/
	public function getQuestCmds(string $quest) : array
	{
		return $this->getPlugin()->questData->get($quest)['cmd'];
	}

	/**
	* @param Player $player
	* @return bool
	*/	
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
					$player->sendMessage($this->getPlugin()->getConfig()->get('Quantity-item'));
					return false;
				}
				$player->sendMessage($this->getPlugin()->getConfig()->get('Need-item'));
				return false;
			}
			$player->sendMessage($this->getPlugin()->getConfig()->get('No-quest'));
			return false;
		}
		$player->sendMessage("§l§7Creative mode is not allowed.");
		return false;
	}

	/**
	* @param Player $player
	*/
	public function showQuest(Player $player) {
		$quest = $this->getPlayerQuest($player);
		$form = new SimpleForm(function(Player $player, $data) use ($quest){
			if(is_null($data)) return;
			if($this->hasQuest($player)){
				$dataq = $this->getPlugin()->questData->get($quest);
				if($data == 1){
					$this->removePlayerQuest($player);
					$player->sendMessage($this->getPlugin()->getConfig()->get('Succes-cancel-quest'));
					return;
				}
				$this->showInfo($player, $quest);
			}else{
				$this->sendQuestApplyForm($player);
			}
		});
		$form->setTitle($this->getPlugin()->getConfig()->get('Title-form'));
		if($this->hasQuest($player)){
			$form->addButton($this->getQuestTitle($quest));
			$form->addButton($this->getPlugin()->getConfig()->get('Cancel-quest-button'));
		}else{
			$form->addButton($this->getPlugin()->getConfig()->get('No-quest-button'));
		}
		$form->sendToPlayer($player);
	}

	/**
	* @param Player $player
	* @param string $quest
	*/
	public function showInfo(Player $player,string $quest){
		$form = new CustomForm(function(Player $player, ?array $data){
			if(is_null($data)) $this->getPlugin()->sendForm($player);
		});
		$title = $this->getQuestTitle($quest);
		$info = $this->getQuestInfo($quest);
		$form->setTitle($this->getPlugin()->getConfig()->get('Title-form'));
		$form->addLabel("§l".$info);
		$form->sendToPlayer($player);
	}

	/**
	* @param Player $player
	* @param string $quest
	*/
	public function sendQuestApplyForm(Player $player){
		$form = new SimpleForm(function(Player $player, ?int $data){
			if(is_null($data)) return;
			$button = $data;
			$list = array_keys( $this->getPlugin()->questData->getAll() );
			$quest = $list[ $button ];
			$this->questCache[ $player->getName() ] = $quest;
			$this->sendQuestInfo($player, $quest);
		});
		$form->setTitle($this->getPlugin()->getConfig()->get('Title-form'));
		if($this->hasQuest($player)){
			$form->setContent($this->getPlugin()->getConfig()->get('Have-quest'));
		}
		foreach( array_keys($this->getPlugin()->questData->getAll()) as $questid){
			$form->addButton($this->getPlugin()->questData->getNested($questid.".title"));
		}
        $form->sendToPlayer($player);
    }
	
	/**
	* @param Player $player
	* @param string $quest
	*/
	public function sendQuestInfo(Player $player, string $quest){
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
		$form->setContent("§fQuest-name:§a ". $this->getQuestTitle($quest). "\n§f-§6 ". $this->getQuestInfo($quest));
		$form->setButton1($this->getPlugin()->getConfig()->get('Button1'));
		$form->setButton2($this->getPlugin()->getConfig()->get('Button2'));
        	$form->sendToPlayer($player);
	}
}
