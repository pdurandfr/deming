<?php

namespace App\Console\Commands;

use App\Control;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deming:send-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications for controls';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::debug('SendNotifications - Start.');

        Log::debug('SendNotifications - day '. Carbon::now()->day);

        if ($this->needCheck()) {
            // Check for control
            Log::debug('SendNotifications - check');

            $controls = Control
                ::whereNull('realisation_date')
                    ->where('plan_date', '<=', Carbon::now()
                        ->addDays(intval(config('deming.notification.expire-delay')))->toDateString())
                    ->orderBy('plan_date')
                    ->count();

            Log::debug(
                $controls .
                ' control(s) will expire within '.
                config('deming.notification.expire-delay') .
                ' days.'
            );

            // Loop on all users
            $users = User::all();
            foreach ($users as $user) {
                // get controls
                $controls = Control::whereNull('realisation_date')
                    ->join('control_user', 'control_id', '=', 'control_id')
                    ->where('user_id', '=', $user->id)
                    ->where('plan_date', '<=', Carbon::now()
                        ->addDays(intval(config('deming.notification.expire-delay')))->toDateString())
                    ->get();
                if ($controls->count() > 0) {
                    $txt = 'Liste des contrôles à réaliser<br><br>';
                    $txt .= '<table>';
                    foreach ($controls as $control) {
                        $txt .= '<tr><td>';
                        $txt .= '<a href="' . url('/measures/' . $control->measure_id) . '">'. $control->clause . '</a> &nbsp; - &nbsp; '. $control->name;
                        $txt .= '</td>';
                        $txt .= '<td>';
                        $txt .= '<a href="' . url('/control/show/'. $control->id) . '">';
                        $txt .= '<b>';
                        if (strtotime($control->plan_date) >= strtotime('now')) {
                            $txt .= "<font color='green'>" . $control->plan_date .' </font>';
                        } else {
                            $txt .= "<font color='red'>" . $control->plan_date . '</font>';
                        }
                        $txt .= '</b>';
                        $txt .= '</a>';
                        $txt .= '</td></tr>';
                    }
                    $txt .= '</table>';

                    // send notification
                    $mail_from = config('deming.notification.mail-from');
                    $headers = [
                        'MIME-Version: 1.0',
                        'Content-type: text/html;charset=UTF-8',
                        'From: '. $mail_from,
                    ];
                    $to_email = $user->email;
                    $mailSubject = config('deming.notification.mail-subject');
                    $message = $txt;

                    // Send mail
                    if (mail($to_email, $mailSubject, $message, implode("\r\n", $headers), ' -f'. $mail_from)) {
                        Log::debug('Mail sent to '.$to_email);
                    } else {
                        Log::debug('Email sending fail.');
                    }
                }
            }
        } else {
            Log::debug('SendNotifications - no check');
        }

        Log::debug('SendNotifications - DONE.');
    }

    /**
     * return true if check is needed
     *
     * @return bool
     */
    private function needCheck()
    {
        $check_frequency = config('deming.notification.frequency');

        return // Daily
            ($check_frequency === '1') ||
            // Weekly
            (($check_frequency === '7') && (Carbon::now()->dayOfWeek === 1)) ||
            // Every two weeks
            (($check_frequency === '15') && (Carbon::now()->dayOfWeek === 15)) ||
            // Monthly
            (($check_frequency === '30') && (Carbon::now()->day === 1));
    }
}