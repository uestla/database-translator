<?php

/**
 * This file is part of the DatabaseTranslator library
 *
 * Copyright (c) 2014 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/database-translator
 */

namespace DatabaseTranslator;

use Nette;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Database\Context;

/*

SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = 'SYSTEM';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `lang`;
CREATE TABLE `lang` (
  `shortcut` varchar(2) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`shortcut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;


DROP TABLE IF EXISTS `translation`;
CREATE TABLE `translation` (
  `idf` varchar(255) COLLATE utf8_czech_ci NOT NULL COMMENT 'translation identifier',
  `lang` varchar(2) COLLATE utf8_czech_ci NOT NULL,
  `amount_type` enum('1','2','3') COLLATE utf8_czech_ci NOT NULL DEFAULT '1' COMMENT '[1] => [one], [2] => [two, three, four], [3] => [rest...]',
  `translation` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`idf`,`lang`,`amount_type`),
  KEY `lang` (`lang`),
  CONSTRAINT `translation_ibfk_1` FOREIGN KEY (`lang`) REFERENCES `lang` (`shortcut`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

 */


class DatabaseTranslator implements Nette\Localization\ITranslator
{

	/** @var Context */
	private $database;

	/** @var Cache */
	private $cache;

	/** @var string */
	private $lang;

	/** @var array */
	private $langs;

	/** @var array */
	private $dictionairy;


	const TABLE_LANG = 'lang';
	const KEY_LANGS = 'langs';
	const TABLE_TRANS = 'translation';
	const KEY_DICTIONAIRY = 'dictionairy-';


	/**
	 * @param  Context $database
	 * @param  IStorage $storage
	 */
	function __construct(Context $database, IStorage $storage)
	{
		$this->database = $database;
		$this->cache = new Cache($storage, __CLASS__);
	}


	/** @return array */
	function getLangs()
	{
		$this->loadLangs();
		return array_keys($this->langs);
	}


	/** @return string */
	function getLang()
	{
		return $this->lang;
	}


	/**
	 * @param  string $lang
	 * @return DatabaseTranslator
	 */
	function setLang($lang)
	{
		$this->loadLangs();

		if (!isset($this->langs[$lang])) {
			throw new Nette\InvalidArgumentException("Language '$lang' is not supported.");
		}

		$this->lang !== $lang && $this->invalidate();
		$this->lang = $lang;
		return $this;
	}


	/**
	 * @param  string $message translation identifier
	 * @param  int $count amount
	 * @return string
	 */
	function translate($message, $count = NULL)
	{
		if ($this->lang === NULL) {
			throw new Nette\InvalidStateException('Language not set.');
		}

		$this->loadDictionairy();
		$count === NULL || $count = (int) $count;
		$amountType = $count === NULL || $count === 1 ? 1 : ($count === 2 || $count === 3 || $count === 4 ? 2 : 3);

		if (isset($this->dictionairy[$message][$amountType])) {
			$translation = $this->dictionairy[$message][$amountType];

		} else {
			$translation = $message;
			$this->addTranslation($message, $amountType, $translation);
		}

		return $count === NULL ? $translation : sprintf($translation, $count);
	}


	/** @return DatabaseTranslator */
	private function loadLangs()
	{
		if ($this->langs === NULL) {
			$key = static::KEY_LANGS;
			if (!isset($this->cache[$key])) {
				$langs = array();
				foreach ($this->database->table(self::TABLE_LANG) as $lang) {
					$langs[$lang->shortcut] = $lang->name;
				}

				$this->langs = $this->cache->save($key, $langs);

			} else {
				$this->langs = $this->cache[$key];
			}
		}

		return $this;
	}


	/** @return DatabaseTranslator */
	private function loadDictionairy()
	{
		if ($this->dictionairy === NULL) {
			$key = static::KEY_DICTIONAIRY . $this->lang;
			if (!isset($this->cache[$key])) {
				$dictionairy = array();
				foreach ($this->database->table(static::TABLE_TRANS)->where('lang', $this->lang) as $record) {
					$dictionairy[$record->idf][$record->amount_type] = $record->translation;
				}

				$this->dictionairy = $this->cache->save($key, $dictionairy);

			} else {
				$this->dictionairy = $this->cache[$key];
			}
		}

		return $this;
	}


	/**
	 * @param  string $idf
	 * @param  int $amountType
	 * @param  string $translation
	 * @return DatabaseTranslator
	 */
	private function addTranslation($idf, $amountType, $translation)
	{
		try {
			$this->database->beginTransaction();

				$this->database->table(self::TABLE_TRANS)->insert(array(
					'idf' => $idf,
					'lang' => $this->lang,
					'amount_type' => $amountType,
					'translation' => $translation,
				));

			$this->database->commit();

		} catch (\PDOException $e) {
			$this->database->rollBack();
			throw $e;
		}

		$this->invalidate(TRUE);
		return $this;
	}


	/**
	 * @param  bool $cache
	 * @return void
	 */
	private function invalidate($cache = FALSE)
	{
		$this->dictionairy = NULL;

		if ($cache) {
			unset($this->cache[static::KEY_DICTIONAIRY . $this->lang]);
		}
	}

}
