<?php

class IcsParser {
    private $icsFolder;
    private $useDatabase;
    private $db;
    private $dbPath;
    private $eventId;

    /**
     * Constructor
     *
     * @param string $icsFolder Path to ICS folder (default: 'ics')
     * @param bool $useDatabase Use SQLite database instead of reading files directly (default: true)
     * @param string $dbPath Path to SQLite database file (default: 'calendar.db')
     * @param int|null $eventId Filter by event_id (null = all programs)
     */
    public function __construct($icsFolder = 'ics', $useDatabase = true, $dbPath = 'data/calendar.db', $eventId = null) {
        $this->icsFolder = rtrim($icsFolder, '/');
        $this->useDatabase = $useDatabase;
        $this->dbPath = $dbPath;
        $this->eventId = $eventId;

        // เชื่อมต่อ database ถ้าเลือกใช้ database mode
        if ($this->useDatabase) {
            $this->connectDatabase();
        }
    }

    /**
     * เชื่อมต่อ SQLite database
     */
    private function connectDatabase() {
        try {
            $this->db = new PDO('sqlite:' . $this->dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // ถ้าเชื่อมต่อไม่ได้ ให้ fallback ไปใช้ไฟล์แทน
            error_log("Failed to connect to database: " . $e->getMessage());
            $this->useDatabase = false;
            $this->db = null;
        }
    }

    /**
     * อ่าน events ทั้งหมด (จาก database หรือไฟล์)
     */
    public function getAllEvents() {
        if ($this->useDatabase && $this->db) {
            return $this->getAllEventsFromDatabase();
        } else {
            return $this->getAllEventsFromFiles();
        }
    }

    /**
     * อ่าน events จาก SQLite database
     */
    private function getAllEventsFromDatabase() {
        $events = [];

        try {
            if ($this->eventId !== null) {
                $stmt = $this->db->prepare("
                    SELECT id, uid, title, start, end, location, organizer, description, categories, event_id
                    FROM programs
                    WHERE event_id = :event_id
                    ORDER BY start ASC
                ");
                $stmt->execute([':event_id' => $this->eventId]);
            } else {
                $stmt = $this->db->query("
                    SELECT id, uid, title, start, end, location, organizer, description, categories, event_id
                    FROM programs
                    ORDER BY start ASC
                ");
            }

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $events[] = $row;
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
        }

        return $events;
    }

    /**
     * อ่าน events จากไฟล์ .ics (backward compatibility)
     */
    private function getAllEventsFromFiles() {
        $events = [];

        if (!is_dir($this->icsFolder)) {
            return $events;
        }

        $files = glob($this->icsFolder . '/*.ics');

        foreach ($files as $file) {
            $fileEvents = $this->parseIcsFile($file);
            $events = array_merge($events, $fileEvents);
        }

        // เรียงตามวันที่
        usort($events, function($a, $b) {
            return strtotime($a['start']) - strtotime($b['start']);
        });

        return $events;
    }

    /**
     * Parse ไฟล์ .ics เดียว
     */
    private function parseIcsFile($filePath) {
        $events = [];
        $content = file_get_contents($filePath);

        if ($content === false) {
            return $events;
        }

        // แยก VEVENT ออกมา
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $matches);

        foreach ($matches[1] as $eventData) {
            $event = $this->parseEvent($eventData);
            if ($event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Parse ข้อมูล event เดียว
     * (public เพื่อให้ import script ใช้งานได้)
     */
    public function parseEvent($eventData) {
        $event = [
            'title' => '',
            'start' => '',
            'end' => '',
            'location' => '',
            'organizer' => '',
            'description' => '',
            'uid' => '',
            'categories' => ''
        ];

        // Parse แต่ละบรรทัด
        $lines = explode("\n", $eventData);
        $currentField = '';

        foreach ($lines as $line) {
            $line = trim($line);

            // จัดการ line ที่ยาวต่อจากบรรทัดก่อน (ขึ้นต้นด้วยช่องว่าง)
            if (preg_match('/^\s/', $line)) {
                $line = trim($line);
                $event[$currentField] .= $line;
                continue;
            }

            // SUMMARY (ชื่อ event)
            if (preg_match('/^SUMMARY:(.*)/', $line, $m)) {
                $event['title'] = $this->decodeIcsValue(trim($m[1]));
                $currentField = 'title';
            }
            // DTSTART (เวลาเริ่ม)
            elseif (preg_match('/^DTSTART[;:](.*)/', $line, $m)) {
                $event['start'] = $this->parseDateTime(trim($m[1]));
                $currentField = 'start';
            }
            // DTEND (เวลาสิ้นสุด)
            elseif (preg_match('/^DTEND[;:](.*)/', $line, $m)) {
                $event['end'] = $this->parseDateTime(trim($m[1]));
                $currentField = 'end';
            }
            // LOCATION (เวที)
            elseif (preg_match('/^LOCATION:(.*)/', $line, $m)) {
                $event['location'] = $this->decodeIcsValue(trim($m[1]));
                $currentField = 'location';
            }
            // ORGANIZER (วง)
            elseif (preg_match('/^ORGANIZER[;:](.*)/', $line, $m)) {
                $value = trim($m[1]);
                // ถ้ามี CN= (Common Name) ให้ใช้ค่านั้น
                // ลองหา CN="..." ก่อน (มี double quotes)
                if (preg_match('/CN="([^"]+)"/', $value, $cn)) {
                    $event['organizer'] = $this->decodeIcsValue($cn[1]);
                }
                // ถ้าไม่มี quotes ให้หา CN=... จนถึง :mailto หรือจบบรรทัด
                elseif (preg_match('/CN=([^;]+?)(?::mailto|$)/', $value, $cn)) {
                    $event['organizer'] = $this->decodeIcsValue(trim($cn[1]));
                } else {
                    // ไม่งั้นใช้ค่าทั้งหมด (เอา mailto: ออก)
                    $event['organizer'] = $this->decodeIcsValue(str_replace('mailto:', '', $value));
                }
                $currentField = 'organizer';
            }
            // DESCRIPTION
            elseif (preg_match('/^DESCRIPTION:(.*)/', $line, $m)) {
                $event['description'] = $this->decodeIcsValue(trim($m[1]));
                $currentField = 'description';
            }
            // UID
            elseif (preg_match('/^UID:(.*)/', $line, $m)) {
                $event['uid'] = trim($m[1]);
                $currentField = 'uid';
            }
            // CATEGORIES
            elseif (preg_match('/^CATEGORIES:(.*)/', $line, $m)) {
                $event['categories'] = $this->decodeIcsValue(trim($m[1]));
                $currentField = 'categories';
            }
        }

        // ต้องมีอย่างน้อย title และ start
        if (empty($event['title']) || empty($event['start'])) {
            return null;
        }

        // ถ้าไม่มี UID ให้สร้างอัตโนมัติ (สำหรับไฟล์ที่ไม่มี UID)
        if (empty($event['uid'])) {
            // สร้าง UID จาก hash ของ title + start + location
            $event['uid'] = md5($event['title'] . $event['start'] . $event['location']) . '@auto-generated.local';
        }

        // ถ้าไม่มี categories แต่มี organizer ให้ใช้ organizer แทน
        if (empty($event['categories']) && !empty($event['organizer'])) {
            $event['categories'] = $event['organizer'];
        }

        // ถ้าไม่มี end ให้ใช้ start
        if (empty($event['end'])) {
            $event['end'] = $event['start'];
        }

        return $event;
    }

    /**
     * แปลงรูปแบบวันที่จาก ICS เป็น ISO 8601
     */
    private function parseDateTime($dateStr) {
        // ลบ TZID= ออก
        $dateStr = preg_replace('/^[^:]+:/', '', $dateStr);
        $dateStr = trim($dateStr);

        // รูปแบบ: 20240101T120000Z หรือ 20240101T120000
        if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})Z?$/', $dateStr, $m)) {
            return sprintf('%s-%s-%sT%s:%s:%s', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]);
        }
        // รูปแบบ: 20240101 (วันทั้งวัน)
        elseif (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $m)) {
            return sprintf('%s-%s-%s', $m[1], $m[2], $m[3]);
        }

        return $dateStr;
    }

    /**
     * Decode ค่าจาก ICS (จัดการ escape characters)
     */
    private function decodeIcsValue($value) {
        $value = str_replace('\\n', "\n", $value);
        $value = str_replace('\\,', ',', $value);
        $value = str_replace('\\;', ';', $value);
        $value = str_replace('\\\\', '\\', $value);
        return $value;
    }

    /**
     * ดึงรายการวงทั้งหมดที่ไม่ซ้ำกัน
     * รองรับ CATEGORIES ที่มีหลายค่า (แยกด้วย comma)
     */
    public function getAllOrganizers() {
        if ($this->useDatabase && $this->db) {
            return $this->getAllOrganizersFromDatabase();
        } else {
            return $this->getAllOrganizersFromEvents();
        }
    }

    /**
     * ดึงรายการวงจาก database (เร็วกว่า)
     */
    private function getAllOrganizersFromDatabase() {
        $organizers = [];

        try {
            if ($this->eventId !== null) {
                $stmt = $this->db->prepare("
                    SELECT DISTINCT categories
                    FROM programs
                    WHERE categories IS NOT NULL AND categories != ''
                    AND event_id = :event_id
                ");
                $stmt->execute([':event_id' => $this->eventId]);
            } else {
                $stmt = $this->db->query("
                    SELECT DISTINCT categories
                    FROM programs
                    WHERE categories IS NOT NULL AND categories != ''
                ");
            }

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // แยก categories ด้วย comma
                $categories = explode(',', $row['categories']);
                foreach ($categories as $category) {
                    $category = trim($category);
                    if (!empty($category)) {
                        $organizers[] = $category;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
        }

        $organizers = array_unique($organizers);
        sort($organizers);
        return $organizers;
    }

    /**
     * ดึงรายการวงจาก events array (fallback)
     * 🚀 Optimization: เลือก DISTINCT จาก database แทนดึงทั้งหมด
     */
    private function getAllOrganizersFromEvents() {
        // ถ้าใช้ database mode ให้ query DISTINCT แทน
        if ($this->useDatabase && $this->db) {
            $organizers = [];

            try {
                // Query only categories column to reduce data transfer and memory
                $stmt = $this->db->query("
                    SELECT DISTINCT categories FROM programs 
                    WHERE categories IS NOT NULL AND categories != ''
                    ORDER BY categories
                ");
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($row['categories'])) {
                        $categories = array_map('trim', explode(',', $row['categories']));
                        $organizers = array_merge($organizers, array_filter($categories));
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                // Fallback to file mode
                return $this->getAllOrganizersFromEventsFile();
            }

            $organizers = array_unique($organizers);
            sort($organizers);
            return $organizers;
        } else {
            // File mode fallback
            return $this->getAllOrganizersFromEventsFile();
        }
    }

    /**
     * Fallback method: ดึงข้อมูลจากไฟล์ .ics
     */
    private function getAllOrganizersFromEventsFile() {
        $events = $this->getAllEventsFromFiles();
        $organizers = [];

        foreach ($events as $event) {
            if (!empty($event['categories'])) {
                $categories = explode(',', $event['categories']);
                foreach ($categories as $category) {
                    $category = trim($category);
                    if (!empty($category)) {
                        $organizers[] = $category;
                    }
                }
            }
        }

        $organizers = array_unique($organizers);
        sort($organizers);
        return $organizers;
    }

    /**
     * ดึงรายการเวทีทั้งหมดที่ไม่ซ้ำกัน
     */
    public function getAllLocations() {
        if ($this->useDatabase && $this->db) {
            return $this->getAllLocationsFromDatabase();
        } else {
            return $this->getAllLocationsFromEvents();
        }
    }

    /**
     * ดึงรายการเวทีจาก database (เร็วกว่า)
     */
    private function getAllLocationsFromDatabase() {
        $locations = [];

        try {
            if ($this->eventId !== null) {
                $stmt = $this->db->prepare("
                    SELECT DISTINCT location
                    FROM programs
                    WHERE location IS NOT NULL AND location != ''
                    AND event_id = :event_id
                    ORDER BY location ASC
                ");
                $stmt->execute([':event_id' => $this->eventId]);
            } else {
                $stmt = $this->db->query("
                    SELECT DISTINCT location
                    FROM programs
                    WHERE location IS NOT NULL AND location != ''
                    ORDER BY location ASC
                ");
            }

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $locations[] = $row['location'];
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
        }

        return $locations;
    }

    /**
     * ดึงรายการเวทีจาก events array (fallback)
     * 🚀 Optimization: ใช้ query DISTINCT แทนดึงทั้งหมด
     */
    private function getAllLocationsFromEvents() {
        // ถ้าใช้ database mode ให้ query DISTINCT แทน
        if ($this->useDatabase && $this->db) {
            $locations = [];

            try {
                $stmt = $this->db->query("
                    SELECT DISTINCT location FROM programs 
                    WHERE location IS NOT NULL AND location != ''
                    ORDER BY location ASC
                ");

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $locations[] = $row['location'];
                }
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                // Fallback: read from file if database fails
                return $this->getAllLocationsFromEventsFile();
            }

            return array_filter($locations);
        } else {
            // File mode fallback
            return $this->getAllLocationsFromEventsFile();
        }
    }

    /**
     * Fallback method: ดึงข้อมูลจากไฟล์ .ics
     */
    private function getAllLocationsFromEventsFile() {
        $events = $this->getAllEventsFromFiles();
        $locations = array_unique(array_column($events, 'location'));
        sort($locations);
        return array_filter($locations);
    }
}
