<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['db_conversations'])) {
    $_SESSION['db_conversations'] = [];
}

if (!isset($_SESSION['db_messages'])) {
    $_SESSION['db_messages'] = [];
}

if (!isset($_SESSION['db_memories'])) {
    $_SESSION['db_memories'] = [];
}

return new class {
    public function query($sql) {
        return null;
    }
    
    public function prepare($sql) {
        return new class($sql) {
            private $sql;
            
            public function __construct($sql) {
                $this->sql = $sql;
            }
            
            public function execute($params = []) {
                return true;
            }
            
            public function fetch($mode = null) {
                return [];
            }
            
            public function fetchAll($mode = null) {
                return [];
            }
            
            public function fetchColumn() {
                return '';
            }
        };
    }
    
    public function lastInsertId() {
        return 0;
    }
};
