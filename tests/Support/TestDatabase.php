<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Support;

use Gibbon\Module\BigBlueButton\Database\Schema;
use PHPUnit\Framework\TestCase;

/**
 * A scratch MariaDB database (BBB_TEST_DB_DSN) with the module tables plus the minimal slice of
 * Gibbon core tables the module reads. Recreated per test class.
 */
final class TestDatabase
{
    private const CORE_TABLES = [
        "CREATE TABLE gibbonCourse (gibbonCourseID INT(8) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT PRIMARY KEY, nameShort VARCHAR(12) NOT NULL)",
        "CREATE TABLE gibbonCourseClass (gibbonCourseClassID INT(8) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT PRIMARY KEY, gibbonCourseID INT(8) UNSIGNED ZEROFILL NOT NULL, nameShort VARCHAR(8) NOT NULL)",
        "CREATE TABLE gibbonCourseClassPerson (gibbonCourseClassPersonID INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT PRIMARY KEY, gibbonCourseClassID INT(8) UNSIGNED ZEROFILL NOT NULL, gibbonPersonID INT(10) UNSIGNED ZEROFILL NOT NULL, role ENUM('Student','Teacher','Assistant','Technician','Parent','Student - Left','Teacher - Left') NOT NULL)",
        "CREATE TABLE gibbonPlannerEntry (gibbonPlannerEntryID INT(14) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT PRIMARY KEY, gibbonCourseClassID INT(8) UNSIGNED ZEROFILL NOT NULL, date DATE NOT NULL, timeStart TIME NOT NULL, timeEnd TIME NOT NULL, name VARCHAR(50) NOT NULL, viewableStudents ENUM('Y','N') NOT NULL DEFAULT 'Y', viewableParents ENUM('Y','N') NOT NULL DEFAULT 'N', fields TEXT NOT NULL)",
        "CREATE TABLE gibbonPlannerEntryGuest (gibbonPlannerEntryGuestID INT(16) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT PRIMARY KEY, gibbonPlannerEntryID INT(14) UNSIGNED ZEROFILL NOT NULL, gibbonPersonID INT(10) UNSIGNED ZEROFILL NOT NULL, role ENUM('Guest Student','Guest Teacher','Guest Assistant','Guest Technician','Guest Parent','Other') NOT NULL)",
        "CREATE TABLE gibbonPerson (gibbonPersonID INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT PRIMARY KEY, status ENUM('Full','Expected','Left','Pending Approval') NOT NULL DEFAULT 'Full', dateStart DATE NULL, dateEnd DATE NULL)",
        "CREATE TABLE gibbonFamily (gibbonFamilyID INT(7) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT PRIMARY KEY)",
        "CREATE TABLE gibbonFamilyAdult (gibbonFamilyAdultID INT(8) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT PRIMARY KEY, gibbonFamilyID INT(7) UNSIGNED ZEROFILL NOT NULL, gibbonPersonID INT(10) UNSIGNED ZEROFILL NOT NULL, childDataAccess ENUM('Y','N') NOT NULL)",
        "CREATE TABLE gibbonFamilyChild (gibbonFamilyChildID INT(8) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT PRIMARY KEY, gibbonFamilyID INT(7) UNSIGNED ZEROFILL NOT NULL, gibbonPersonID INT(10) UNSIGNED ZEROFILL NOT NULL)",
        "CREATE TABLE gibbonSetting (gibbonSettingID INT(5) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT PRIMARY KEY, scope VARCHAR(50) NOT NULL, name VARCHAR(50) NOT NULL, nameDisplay VARCHAR(60) NOT NULL DEFAULT '', description TEXT NOT NULL, value TEXT NOT NULL)",
        "CREATE TABLE gibbonCustomField (gibbonCustomFieldID INT(4) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT PRIMARY KEY, context VARCHAR(60) NOT NULL, name VARCHAR(50) NOT NULL, active ENUM('Y','N') NOT NULL DEFAULT 'Y')",
    ];

    public static function connect(): \PDO
    {
        $dsn = getenv('BBB_TEST_DB_DSN');
        if ($dsn === false || $dsn === '') {
            TestCase::markTestSkipped('BBB_TEST_DB_DSN not set (run via the harness "tests" service)');
        }
        $pdo = new \PDO(str_replace('dbname=bbb_test;', '', $dsn), (string) getenv('BBB_TEST_DB_USER'), (string) getenv('BBB_TEST_DB_PASSWORD'), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('DROP DATABASE IF EXISTS bbb_test');
        $pdo->exec('CREATE DATABASE bbb_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE bbb_test');
        foreach ([...self::CORE_TABLES, ...Schema::tablesIfNotExists()] as $sql) {
            $pdo->exec($sql);
        }

        return $pdo;
    }

    /** @param array<string, string> $values BigBlueButton settings by name */
    public static function seedSettings(\PDO $pdo, array $values): void
    {
        $insert = $pdo->prepare("INSERT INTO gibbonSetting (scope, name, description, value) VALUES ('BigBlueButton', :name, '', :value)");
        foreach ($values as $name => $value) {
            $insert->execute(['name' => $name, 'value' => $value]);
        }
    }
}
