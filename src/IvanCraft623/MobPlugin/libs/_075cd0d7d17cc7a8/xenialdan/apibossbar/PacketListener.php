<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_075cd0d7d17cc7a8\xenialdan\apibossbar;

use InvalidArgumentException;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\plugin\Plugin;
use pocketmine\Server;

class PacketListener implements Listener
{
	private static ?Plugin $registrant = null;

	public static function isRegistered(): bool
	{
		return self::$registrant instanceof Plugin;
	}

	public static function getRegistrant(): Plugin
	{
		return self::$registrant ?? throw new \LogicException("Boss bar packet listener is not registered");
	}

	public static function unregister(): void
	{
		self::$registrant = null;
	}

	public static function register(Plugin $plugin): void
	{
		if (self::isRegistered()) {
			return;//silent return
		}

		self::$registrant = $plugin;
		Server::getInstance()->getPluginManager()->registerEvents(new self, $plugin);
	}

	public function onDataPacketReceiveEvent(DataPacketReceiveEvent $e) : void
	{
		if ($e->getPacket() instanceof BossEventPacket) $this->onBossEventPacket($e);
	}

	private function onBossEventPacket(DataPacketReceiveEvent $e) : void
	{
		if (!($pk = $e->getPacket()) instanceof BossEventPacket) throw new InvalidArgumentException(get_class($e->getPacket()) . " is not a " . BossEventPacket::class);
		switch ($pk->eventType) {
			case BossEventPacket::TYPE_REGISTER_PLAYER:
			case BossEventPacket::TYPE_UNREGISTER_PLAYER:
				Server::getInstance()->getLogger()->debug("Got BossEventPacket " . ($pk->eventType === BossEventPacket::TYPE_REGISTER_PLAYER ? "" : "un") . "register by client for player id " . $pk->bossActorUniqueId);
				break;
			default:
				$e->getOrigin()->disconnect("Invalid packet received");
		}
	}

}