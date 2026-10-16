                $dateTimeIn->format('h:i A');
        }


        // =================================================
        // FORMAT TIME-OUT
        // =================================================

        $timeOutDisplay = null;

        if (
            !empty(
                $completedAttendance['time_out']
            )
        ) {

            $dateTimeOut = new DateTime(
                $completedAttendance['time_out'],
                new DateTimeZone('Asia/Manila')
            );

            $dateTimeOut->setTimezone(
                new DateTimeZone('Asia/Manila')
            );

            $timeOutDisplay =
                $dateTimeOut->format('h:i A');
        }


        // =================================================
        // RESPONSE
        // =================================================

        response(
            true,
            'Time-out recorded successfully.',
            [

                'action' =>
                    'time_out',

                'attendance_id' =>
                    (int)
                    $completedAttendance['id'],

                'teacher_id' =>
                    (int)
                    $completedAttendance['teacher_id'],

                'schedule_id' =>
                    (int)
                    $completedAttendance['schedule_id'],

                'session_id' =>
                    (int)
                    $completedAttendance['session_id'],

                'teacher_name' =>
                    $session['teacher_name'],

                'subject_code' =>
                    $session['subject_code'],

                'subject_name' =>
                    $session['subject_name'],

                'year_name' =>
                    $session['year_name'],

                'section_name' =>
                    $session['section_name'],

                'status' =>
                    $completedAttendance['status'],

                'time_in' =>
                    $completedAttendance['time_in'],

                'time_out' =>
                    $completedAttendance['time_out'],

                'time_in_display' =>
                    $timeInDisplay,

                'time_out_display' =>
                    $timeOutDisplay,

                'duration_seconds' =>
                    $durationSeconds,

                'duration' =>
                    $duration,

                'room' =>
                    $session['room'] ?: 'TBA',

                'session_status' =>
                    'completed'
            ]
        );


    } catch (PDOException $e) {

        response(
            false,
            'Unable to record time-out. Please try again.'
        );

    } catch (Exception $e) {

        response(
            false,
            'Unable to calculate attendance duration.'
        );
    }
}


// =========================================================
// FALLBACK
// =========================================================

response(
    false,
    'Unable to determine attendance action.'
);
