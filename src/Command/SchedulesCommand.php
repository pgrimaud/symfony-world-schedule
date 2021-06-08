<?php

namespace App\Command;

use Carbon\Carbon;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\{Table, TableCell, TableCellStyle, TableSeparator};
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class SchedulesCommand extends Command
{
    protected static $defaultName = 'app:schedules';
    protected static $defaultDescription = 'Display SymfonyWorld online scheduling';

    protected function configure()
    {
        $this->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->block(' SymfonyWorld Online 2021 - https://live.symfony.com/2021-world/schedule', null, 'options=bold;bg=blue', '', true);

        $userTimeZone = $this->getTimeZone($io);

        $io->writeln('Your timezone is: ' . $userTimeZone);
        $io->newLine();

        $data = Yaml::parseFile(dirname(__FILE__) . '/../../data/schedules.yaml');

        $now = Carbon::now($userTimeZone);

        $eventsToDisplay = [];
        foreach ($data['events'] as $event) {
            $hoursLeft = Carbon::parse($event['start_at'], $event['timezone'])
                ->setTimezone($userTimeZone)
                ->diffInHours($now, false);

            $minutesLeft = Carbon::parse($event['start_at'], $event['timezone'])
                ->setTimezone($userTimeZone)
                ->diffInMinutes($now, false);

            $daysLeft = Carbon::parse($event['start_at'], $event['timezone'])
                ->setTimezone($userTimeZone)
                ->diffInDays($now, false);

            if ($daysLeft < 0) {
                $daysLeft = abs($daysLeft);
                $eventsToDisplay[] = array_merge($event, ['start_in' => "Start in $daysLeft days."]);
            } elseif ($hoursLeft < 0) {
                $hoursLeft = abs($hoursLeft);
                $eventsToDisplay[] = array_merge($event, ['start_in' => "Start in $hoursLeft hours."]);
            } elseif ($minutesLeft < 0) {
                $minutesLeft = abs($minutesLeft);
                $eventsToDisplay[] = array_merge($event, ['start_in' => "Start in $minutesLeft minutes."]);
            }
        }

        if (count($eventsToDisplay) > 0) {
            $table = new Table($io);

            $table->setHeaders(['Event', 'Location', 'Language', 'Date', 'Start']);
            $table->setRows(array_map(function ($item) use ($userTimeZone) {
                return [
                    $item['name'],
                    $item['location'],
                    $item['language'],
                    Carbon::parse($item['start_at'], $item['timezone'])->setTimezone($userTimeZone)->format('Y/m/d'),
                    $item['start_in'],
                ];
            }, $eventsToDisplay));

            $table->setStyle('box-double');
            $table->render();
            $io->newLine();

            // ask event to display
            $result = $io->choice('Select the event to display', array_map(function ($eventsToDisplay) {
                return $eventsToDisplay['name'];
            }, $eventsToDisplay), 0);

            $eventKey = array_search($result, array_column($eventsToDisplay, 'name'));
            $this->displayEventWithTalks($io, $userTimeZone, $eventsToDisplay[$eventKey]);

        } else {
            $io->text('No events yet.');
        }

        return Command::SUCCESS;
    }

    public function getTimeZone(SymfonyStyle $io): string
    {
        $fileName = getenv('HOME') ? getenv('HOME') . '/.symfony-world' : './.symfony-world';

        $filesystem = new Filesystem();
        if (!$filesystem->exists($fileName)) {
            $timeZone = $this->getSystemTimeZone($io);
            $filesystem->appendToFile($fileName, $timeZone);

            $io->newLine();
        }

        return file_get_contents($fileName);
    }

    private function getSystemTimeZone(SymfonyStyle $io): string
    {
        switch (true) {
            case strpos(php_uname('s'), 'Darwin') !== false:
                // password will be asked?
                $askSudo = shell_exec('sudo -n true 2>&1');

                if ($askSudo !== null) {
                    $io->warning('Please enter your "sudo" password so we can retrieve your timezone:');
                }
                return ltrim(exec('sudo systemsetup -gettimezone'), 'Time Zone: ');

            case strpos(php_uname('s'), 'Linux') !== false:
                if (file_exists('/etc/timezone')) {
                    return ltrim(exec('cat /etc/timezone'));
                }

                return exec('date +%Z');

            case strpos(php_uname('s'), 'Windows') !== false:
                $tz = exec('tzutil /g');
                return ltrim($this->getTimezoneFromWindows($tz));

            default:
                $io->error('Your OS is not supported at this time.');
                die();
        }
    }

    private function getTimezoneFromWindows($tz)
    {
        $json = file_get_contents(__DIR__ . '/../CommandData/windowsZones.json');
        $zones = json_decode($json, true);

        foreach ($zones as $z => $iana) {
            if ($z === $tz) {
                return $iana['iana'][0];
            }
        }
    }

    private function displayEventWithTalks(SymfonyStyle $io, string $userTimeZone, array $event): void
    {
        $io->block(' Welcome to ' . $event['name'], null, 'bg=green', '', true);

        $eventDate = Carbon::parse($event['start_at'], $event['timezone'])->setTimezone($userTimeZone);

        $table = new Table($io);
        $table->setHeaders([
                [
                    new TableCell(), // empty cell to center title
                    new TableCell(
                        $eventDate->format('d F Y'),
                        [
                            'colspan' => count($event['tracks']),
                            'style' => new TableCellStyle([
                                'align' => 'center',
                                'options' => 'bold',
                            ]),
                        ]),
                ],
                [
                    '',
                    new TableCell($event['tracks'][0]['name'], [
                        'style' => new TableCellStyle([
                            'align' => 'center',
                            'fg' => 'green',
                        ]),
                    ]),
                    new TableCell($event['tracks'][1]['name'], [
                        'style' => new TableCellStyle([
                            'align' => 'center',
                            'fg' => 'green',
                        ]),
                    ]),
                ],
            ]
        );

        foreach ($event['schedules'] as $key => $schedule) {
            if ($schedule['talks'][0]['track'] === 'all') {
                $table->addRow([
                    Carbon::parse($schedule['start_at'], $event['timezone'])->setTimezone($userTimeZone)->format('H:i'),
                    new TableCell(
                        $schedule['talks'][0]['name'],
                        [
                            'colspan' => count($event['tracks']),
                            'style' => new TableCellStyle([
                                'align' => 'center',
                                'options' => 'bold',
                            ]),
                        ]
                    ),
                ]);
                if (isset($schedule['talks'][0]['speaker'])) {
                    $table->addRow([
                        '',
                        new TableCell(
                            'by ' . $schedule['talks'][0]['speaker'],
                            [
                                'colspan' => count($event['tracks']),
                                'style' => new TableCellStyle([
                                    'align' => 'center',
                                ]),
                            ]
                        ),
                    ]);
                }
            } else {
                $rowTalk = [
                    Carbon::parse($schedule['start_at'], $event['timezone'])->setTimezone($userTimeZone)->format('H:i'),
                ];

                $rowSpeaker = [''];

                // talk is only on track 2?
                if (count($schedule['talks']) === 1 && $schedule['talks'][0]['track'] === 2) {
                    $rowTalk[] = new TableCell();
                    $rowSpeaker[] = new TableCell();
                }

                foreach ($schedule['talks'] as $talk) {
                    $rowTalk[] = new TableCell(
                        $talk['name'],
                        [
                            'style' => new TableCellStyle([
                                'align' => 'center',
                                'options' => 'bold',
                            ]),
                        ]);

                    if (isset($talk['speaker'])) {
                        $rowSpeaker[] = new TableCell(
                            'by ' . $talk['speaker'],
                            [
                                'style' => new TableCellStyle([
                                    'align' => 'center',
                                ]),
                            ]);
                    }

                }
                $table->addRow($rowTalk);
                $table->addRow($rowSpeaker);
            }

            if ($key < array_key_last($event['schedules'])) {
                $table->addRow(new TableSeparator);
            }
        }

        $table->setStyle('box-double');
        $table->setColumnWidth(1, 60);
        $table->setColumnWidth(2, 60);
        $table->render();
    }
}
