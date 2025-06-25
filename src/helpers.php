<?php

declare(strict_types = 1);

use Hunter\Hunter;

if (! function_exists('hunter')) {
    /**
     * Create a new Hunter instance for the given model class.
     *
     * @param string $modelClass The fully qualified class name of the Eloquent model
     * @throws InvalidArgumentException If the class doesn't exist or doesn't extend Model
     */
    function hunter(string $modelClass): Hunter
    {
        return Hunter::for($modelClass);
    }
}
