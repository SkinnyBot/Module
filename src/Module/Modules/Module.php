<?php
namespace Module\Module\Modules;

use DateTime;
use Skinny\Core\Configure;
use Skinny\Module\ModuleInterface;
use Skinny\Network\Wrapper;
use Skinny\Utility\Command;
use Skinny\Utility\Inflector;

class Module implements ModuleInterface
{
    /**
     * {@inheritDoc}
     *
     * @param \Skinny\Network\Wrapper $wrapper The Wrapper instance.
     * @param array $message The message array.
     *
     * @return void
     */
    public function onCommandMessage(Wrapper $wrapper, $message)
    {
        if ($message['command'] !== 'module') {
            return;
        }

        if (!isset($message['arguments'][1]) && $message['arguments'][0] !== 'loaded') {
            $wrapper->Message->reply(Command::syntax($message));

            return;
        }

        switch ($message['arguments'][0]) {
            case 'load':
                //Load the Module.
                $module = $wrapper->ModuleManager->load($message['arguments'][1]);

                switch ($module) {
                    //AlreadyLoaded
                    case 'AL':
                        $wrapper->Channel->send('The Module `' . $message['arguments'][1] .
                            '` is already loaded.');
                        break;

                    //Loaded
                    case 'L':
                        $wrapper->Channel->send('Module `' . $message['arguments'][1] .
                            '` loaded successfully.');
                        break;

                    //NotFound
                    case 'NF':
                        $wrapper->Channel->send('The Module `' . $message['arguments'][1] . '` was not found.');
                        break;
                }
                break;

            case 'unload':
                //Prevent for loading a file in the memory for nothing.
                if (Configure::read('debug') === false) {
                    $wrapper->Channel->send('You can\'t unload a Module when the debug is `false`.');
                    break;
                }

                //Unload the Module.
                $module = $wrapper->ModuleManager->unload($message['arguments'][1]);

                //AlreadyUnloaded
                if ($module === 'AU') {
                    $wrapper->Channel->send('The Module `' . $message['arguments'][1] .
                        '` is already unloaded or doesn\'t exist.');
                } else {
                    $wrapper->Channel->send('Module `' . $message['arguments'][1] . '` unloaded successfully.');
                }
                break;

            case 'reload':
                //Prevent for loading a file in the memory for nothing.
                if (Configure::read('debug') === false) {
                    $wrapper->Channel->send('You can\'t reload a Module when the debug is `false`.');
                    break;
                }

                //Check if we must reload all Modules.
                if ($message['arguments'][1] == "all") {
                    //Get the list of the loaded Modules.
                    $loadedModules = $wrapper->ModuleManager->getLoadedModules();

                    //For each Modules, we reload it.
                    foreach ($loadedModules as $module) {
                        $this->reloadModule($wrapper, $module);

                        //To avoid spam.
                        usleep(500000);
                    }

                    break;
                }

                //Else there is just one Module to reload.
                $this->reloadModule($wrapper, $message['arguments'][1]);
                break;

            case 'time':
                //Get the UNIX time.
                $time = $wrapper->ModuleManager->timeLoaded($message['arguments'][1]);

                //If $time is false, that mean the Module is not loaded and/or doesn't exist.
                if ($time === false) {
                    $wrapper->Channel->send('This Module is not loaded.');
                    break;
                }

                $seconds = floor(microtime(true) - $time);

                $start = new DateTime("@0");
                $end = new DateTime("@$seconds");

                $wrapper->Channel->send('The Module `' . Inflector::camelize($message['arguments'][1]) .
                    '` is loaded since ' . $start->diff($end)->format('%a days, %h hours, %i minutes and %s seconds.'));
                break;

            case 'loaded':
                //Get the loaded Modules and implode the array as a string.
                $modules = $wrapper->ModuleManager->getLoadedModules();
                $modules = implode("`, `", $modules);

                $wrapper->Channel->send('Modules loaded : `' . Inflector::camelize($modules) . '`.');
                break;

            default:
                $wrapper->Channel->send(Command::unknown($message));
        }
    }

    /**
     * Function to reload a Module and send the response.
     *
     * @param \Skinny\Network\Wrapper $wrapper The Wrapper instance.
     * @param string $module The module to reload.
     *
     * @return void
     */
    protected function reloadModule(Wrapper $wrapper, $module)
    {
        $moduleStatus = $wrapper->ModuleManager->reload($module);

        $module = Inflector::camelize($module);

        switch ($moduleStatus) {
            //AlreadyUnloaded
            case 'AU':
                $wrapper->Channel->send('The Module `' . $module . '` doesn\'t exist and cannot be reloaded.');
                break;

            //AlreadyLoaded
            case 'AL':
                $wrapper->Channel->send('The Module `' . $module . '` is already loaded.');
                break;

            //Loaded
            case 'L':
                $wrapper->Channel->send('Module `' . $module . '` reloaded successfully.');
                break;

            //NotFound
            case 'NF':
                $wrapper->Channel->send('Failed to reload the Module `' . $module . '`.');
                break;
        }
    }
}
