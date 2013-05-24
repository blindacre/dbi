# DBI

A model library for databases. DBI currently supports MySQL, MySQLi, and PDO
database connections.

## Basic Usage

Developers extend the Dbi_Model class to create database queries. Models support
where clauses, joins, and subqueries.

## Examples

    // Create a model that queries a "user" table. The "id" field is an
    // auto-incrementing primary key.
    class Model_User extends Dbi_Model {
        public function __construct() {
            parent::__construct();
            $this->name = 'user';
            $this->addField('id', new Dbi_Field('int', array('10', 'unsigned', 'auto_increment'), '', false));
            $this->addField('name', new Dbi_Field('varchar', array('64'), '', false));
            $this->addField('email', new Dbi_Field('varchar', array('64'), '', false));
            $this->addIndex('primary', array(
                'userid'
            ), 'unique');
        }
    }

    // Iterate through all the users in the table.
    $users = new Model_User();
    foreach ($users->select() as $user) {
        echo $user['name'] . '<br/>';
    }

    // Select a user by ID.
    $user = Model_User::Get(100);

    // Check to see if the user exists.
    if ($user->exists()) {
        echo "User #100 exists.";
    } else {
        echo "User #100 does not exist.";
    }

    // Select the first user named Bob.
    $users = new Model_User();
    $users->where('name = ?', 'Bob');
    $bob = $users->getFirst();

## Known Issues

* Database configuration from Dbi_Schema classes is only supported with MySQL
databases.
