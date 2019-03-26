<?php

error_reporting(0);
ini_set('display_errors', 0);

function adminer_object()
{
    class MyAdminer extends Adminer
    {
        public function login($login, $password)
        {
            return true;
        }

        public function headers()
        {
            header_remove("X-Frame-Options");
        }

        public function name()
        {
            return "<a href='https://covenantsql.io'" . target_blank() . " id='h1'>CQL-Adminer</a>";
        }

        public function loginForm()
        {
            echo "<table cellspacing='0' class='layout'>\n";
            echo "<input type='hidden' name='auth[driver]' value='covenantsql'>\n";

            if (!empty($_SERVER["CQL_ADAPTER_SERVER"])) {
                echo "<input type='hidden' name='auth[server]' value='${_SERVER["CQL_ADAPTER_SERVER"]}'>";
            } else {
                echo $this->loginFormField('server', '<tr><th>' . lang('Server') . '<td>', '<input name="auth[server]" value="' . h(SERVER) . '"      title="hostname[:port]" placeholder="localhost:6000" autocapitalize="off">' . "\n");
            }

            echo $this->loginFormField('db', '<tr><th>' . lang('Database') . '<td>', '<input name="auth[db]" value="' . h($_GET["db"]) . '"       autocapitalize="off">' . "\n");
            echo "</table>\n";
            echo "<p><input type='submit' value='" . lang('Login') . "'>\n";
            echo checkbox("auth[permanent]", 1, $_COOKIE["adminer_permanent"], lang('Permanent login')) . "\n";
        }
    }

    return new MyAdminer;
}

include "./include/bootstrap.inc.php";
include "./include/tmpfile.inc.php";

$enum_length = "'(?:''|[^'\\\\]|\\\\.)*'";
$inout = "IN|OUT|INOUT";

if (isset($_GET["select"]) && ($_POST["edit"] || $_POST["clone"]) && !$_POST["save"]) {
    $_GET["edit"] = $_GET["select"];
}
if (isset($_GET["callf"])) {
    $_GET["call"] = $_GET["callf"];
}
if (isset($_GET["function"])) {
    $_GET["procedure"] = $_GET["function"];
}

if (isset($_GET["download"])) {
    include "./download.inc.php";
} elseif (isset($_GET["table"])) {
    include "./table.inc.php";
} elseif (isset($_GET["schema"])) {
    include "./schema.inc.php";
} elseif (isset($_GET["dump"])) {
    include "./dump.inc.php";
} elseif (isset($_GET["privileges"])) {
    include "./privileges.inc.php";
} elseif (isset($_GET["sql"])) {
    include "./sql.inc.php";
} elseif (isset($_GET["edit"])) {
    include "./edit.inc.php";
} elseif (isset($_GET["create"])) {
    include "./create.inc.php";
} elseif (isset($_GET["indexes"])) {
    include "./indexes.inc.php";
} elseif (isset($_GET["database"])) {
    include "./database.inc.php";
} elseif (isset($_GET["scheme"])) {
    include "./scheme.inc.php";
} elseif (isset($_GET["call"])) {
    include "./call.inc.php";
} elseif (isset($_GET["foreign"])) {
    include "./foreign.inc.php";
} elseif (isset($_GET["view"])) {
    include "./view.inc.php";
} elseif (isset($_GET["event"])) {
    include "./event.inc.php";
} elseif (isset($_GET["procedure"])) {
    include "./procedure.inc.php";
} elseif (isset($_GET["sequence"])) {
    include "./sequence.inc.php";
} elseif (isset($_GET["type"])) {
    include "./type.inc.php";
} elseif (isset($_GET["trigger"])) {
    include "./trigger.inc.php";
} elseif (isset($_GET["user"])) {
    include "./user.inc.php";
} elseif (isset($_GET["processlist"])) {
    include "./processlist.inc.php";
} elseif (isset($_GET["select"])) {
    include "./select.inc.php";
} elseif (isset($_GET["variables"])) {
    include "./variables.inc.php";
} elseif (isset($_GET["script"])) {
    include "./script.inc.php";
} else {
    include "./db.inc.php";
}

// each page calls its own page_header(), if the footer should not be called then the page exits
page_footer();
