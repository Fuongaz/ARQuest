<?php

namespace phuongaz\Quest;


use pocketmine\Server;
use pocketmine\Player;

use pocketmine\plugin\PluginBase;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

use pocketmine\level\level;

use pocketmine\item\Item;
use pocketmine\inventory\Inventory;

use jojoe77777\FormAPI\SimpleForm;

Class Quest extends PluginBase {

	public $db;
	public function onEnable():void {
		$this->saveResource('quests.yml');
		$this->questData = new Config($this->getDataFolder() . "quests.yml", CONFIG::YAML);
		$this->db = new \SQLite3($this->getDataFolder() . "quest.db"); 
		$this->db->exec("CREATE TABLE IF NOT EXISTS pquests (name TEXT PRIMARY KEY COLLATE NOCASE, quest TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS pcompleted (name TEXT PRIMARY KEY COLLATE NOCASE, quests TEXT);");
		$this->quests = new Quests($this); 
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args) : bool 
	{	
		switch (strtolower($command->getName() ))
		{
			case "arquest":
				if (!isset($args[0]))
				{
					$this->sendForm($sender);
					return true;
				}
			break;
		}
		return true;
	}
	public function rca(Player $player, string $string) : void{
		$command = str_replace("{player}", $player->getName(), $string);
		Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), $command);
	}

	public function sendForm($sender) {
		$quest = new Quests($this);
		$form = new SimpleForm(function(Player $player, ?int $data) use ($quest){
			if($data == 0) $quest->sendQuestApplyForm($player);
			if($data == 1) $quest->Completed($player);
			if($data == 2) $quest->showQuest($player);
		});
		$form->setTitle("§l§6Nhiệm Vụ");
		$form->addButton("§l§f•§0 Nhận nhiệm vụ §f•");
		$form->addButton("§l§f•§0 Báo cáo nhiệm vụ §f•");
		$form->addButton("§l§f•§0 Nhiệm vụ của bạn §f•");
		$form->sendToPlayer($sender);
	}
}
		