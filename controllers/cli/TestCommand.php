<?php namespace app\controllers\cli;

use kiss\controllers\cli\Command;

class TestCommand extends Command {
    public function cmdDefault() {
        self::print('Welcome to the default command');
    }
    public function cmdApple() {
        self::print('Welcome to the apple command');
    }
}