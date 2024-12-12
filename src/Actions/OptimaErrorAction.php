<?php

namespace Daxit\OptimaClass\Actions;

class OptimaErrorAction
{
    /**
     * Runs the action.
     *
     * @return string result content
     */
    public function run()
    {
        if (!config('app.debug')) {
            return redirect('/404');
        }
    }

}
