<?php

namespace PavelDotsenko\StrInMatrix\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DemoCommand extends Command
{
    const CASE_SENSITIVE = false;
    const ARG_MATRIX = 'matrix';
    const ARG_WORD = 'word';
    const LAST_CHAR_KEY = '*END*';

    const VERBOSE = true;
    const KEYS_SEPARATOR = ',';
    const CELL_HEADERS_COLOR = 'blue';
    const CELL_HIGHLIGHTED_FG_COLOR = 'bright-yellow';
    const CELL_HIGHLIGHTED_BG_COLOR = null;

    /**
     * The name of the command (the part after "bin/run").
     *
     * @var string
     */
    protected static $defaultName = 'sim';

    /**
     * The command description shown when running "php bin/run list".
     *
     * @var string
     */
    protected static $defaultDescription = 'The main Application command';

    private SymfonyStyle $io;
    private InputInterface $input;
    private int $wordLength;
    private array $matrixArray;
    private array $wordAsArray;
    private array $results;

    protected function configure()
    {
        $this->addArgument(
            self::ARG_MATRIX, InputArgument::OPTIONAL,
            'String of size N^2, that describes square matrix of characters N*N'
        );
        $this->addArgument(
            self::ARG_WORD, InputArgument::OPTIONAL,
            'String that describes given word'
        );
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int 0 if everything went fine, or an exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = &$input;
        $this->io = new SymfonyStyle($this->input, $output);


        $this->processArguments();
        $this->processTraces();

        $this->renderResults();

        return Command::SUCCESS;
    }

    private function processTraces()
    {
        $this->matrixArray = array_chunk(
            str_split($this->input->getArgument(self::ARG_MATRIX)),
            $this->wordLength
        );

        $this->wordAsArray = str_split($this->input->getArgument(self::ARG_WORD));

        $startPoints = $this->findStartPoints();

        foreach ($startPoints as $rowKey => $charKeys) {
            array_map(function ($charKey) use (&$rowKey) {
                $this->results[] = $this->getWordTraces(0, $rowKey, $charKey);
            }, $charKeys);
        }
    }

    private function findStartPoints(): array
    {
        $startPoints = [];

        foreach ($this->matrixArray as $rowKey => $charsRow) {
            if (in_array($this->wordAsArray[0], $charsRow)) {
                $startPoints[$rowKey] = [];
                foreach ($charsRow as $charKey => $char) {
                    if ($char == $this->wordAsArray[0]) {
                        $startPoints[$rowKey][] = $charKey;
                    }
                }
            }
        }

        return $startPoints;
    }

    private function getWordTraces(int $wordCharKey, int $currentRowKey, int $currentCharKey, array $traceStack = []): ?array
    {
        $traceStack[] = $this->glue($currentCharKey, $currentRowKey);

        if ($next = $this->wordAsArray[$wordCharKey + 1] ?? null) {

            $nearby = [
                [$currentRowKey => $currentCharKey + 1],
                [$currentRowKey => $currentCharKey - 1],
                [$currentRowKey + 1 => $currentCharKey],
                [$currentRowKey - 1 => $currentCharKey],
            ];

            $nearbyFound = [];

            foreach ($nearby as $keys) {

                $rowKey = array_key_first($keys);

                if (
                    isset($this->matrixArray[$rowKey]) &&
                    ($char = $this->matrixArray[$rowKey][$keys[$rowKey]] ?? null) &&
                    $char === $next
                ) {
                    $keyString = $this->glue($keys[$rowKey], $rowKey);
                    if (!in_array($keyString, $traceStack)) $nearbyFound[] = $keyString;
                }
            }

            if ($nearbyFound) {
                $rowKey = (int)strstr($nearbyFound[0], self::KEYS_SEPARATOR, true);
                $charKey = (int)strstr($nearbyFound[0], self::KEYS_SEPARATOR)[1];
                unset($nearbyFound[0]);

                if ($nearbyFound) {

                    $traceStackBase = $traceStack;

                    foreach ($nearbyFound as $keyString) {
                        $row = (int)strstr($keyString, self::KEYS_SEPARATOR, true);
                        $char = (int)strstr($keyString, self::KEYS_SEPARATOR)[1];
                        $this->results[] = $this->getWordTraces($wordCharKey + 1, $char, $row, $traceStackBase);
                    }
                }

                $traceStack = $this->getWordTraces($wordCharKey + 1, $charKey, $rowKey, $traceStack);
            }
        }

        return $traceStack;
    }

    private function renderResults()
    {
        $this->results = array_filter($this->results, function ($res) {
            return count($res) === $this->wordLength;
        });

        $unique = [];

        array_map(function ($res) use (&$unique) {
            if (!in_array($res,$unique) && !in_array(array_reverse($res), $unique)) {
                $unique[] = $res;
            }
        }, $this->results);

        $this->results = $unique;

        if (empty(array_filter($this->results))) {
            $this->io->error(sprintf('Whoops! The word "%s" not found in the matrix', $this->input->getArgument(self::ARG_WORD)));

            if (self::VERBOSE) {
                $this->io->block(
                    array_map(function ($row) {
                        return implode('   ', $row);
                    }, $this->matrixArray),
                    null,
                    'fg=yellow',
                    str_repeat(' ', 8)
                );
            }

            exit(Command::INVALID);
        }

        $this->io->write(
            count($this->results) > 1
                ? sprintf(' <fg=green>Results found: </><bg=blue;options=bold> %u </>', count($this->results))
                : 'Result:',
            true);
        $this->io->newLine();

        foreach ($this->results as $row) {
            $resultsString = implode(' ', array_map(fn($res) => sprintf('<bg=green;fg=black;options=bold> %s </>', $res), $row));
            $this->io->writeln(sprintf(' Trace: %s', $resultsString));
            $this->io->newLine();
            if (self::VERBOSE) {
                $this->io->writeln($this->renderResultsTable($row));
                $this->io->newLine();
            }
        }

        $this->io->newLine();
    }

    private function renderResultsTable(array $resultKeys): string
    {
        $buffer = new BufferedOutput(null, true);
        $table = new Table($buffer);
        $tableStyle = $table->getStyleDefinition('box');
        $tableStyle->setPadType(STR_PAD_BOTH);
        $tableStyle->setCellHeaderFormat('<fg=' . self::CELL_HEADERS_COLOR . ';options=bold>%s</>');
        $table->setStyle($tableStyle);
        $table->setHeaders([null, ...array_keys($this->matrixArray)]);

        foreach ($this->matrixArray as $yKey => $row) {
            $rowTitle = new TableCell($yKey, [
                'style' => new TableCellStyle([
                    'fg'      => self::CELL_HEADERS_COLOR,
                    'options' => 'bold',
                ])
            ]);
            $tableRow = [$rowTitle];

            foreach ($row as $xKey => $char) {
                $isDefault = !in_array($this->glue($xKey, $yKey), $resultKeys) ? 'default' : null;
                $options = [
                    'style' => new TableCellStyle([
                        'bg'      => $isDefault ?: self::CELL_HIGHLIGHTED_BG_COLOR,
                        'fg'      => $isDefault ?: self::CELL_HIGHLIGHTED_FG_COLOR,
                        'options' => $isDefault ? null : 'bold'
                    ]),
                ];
                $tableRow[] = new TableCell($char, $options);
            }

            $table->setRow($yKey, $tableRow);
        }

        $table->render();

        return $buffer->fetch();
    }

    private function processArguments()
    {
        $this->processMatrixArgument();
        $this->processWordArgument();

        $this->io->block('Arguments accepted. Processing...', null, 'info');
    }

    private function processMatrixArgument()
    {
        $matrix = $this->input->getArgument(self::ARG_MATRIX)
            ?: $this->io->ask(sprintf('Enter the "%s" to proceed:', ucfirst(self::ARG_MATRIX)));

        $this->validateMatrix($this->filled($matrix));
        self::CASE_SENSITIVE ?: $matrix = strtoupper($matrix);

        $this->input->setArgument(self::ARG_MATRIX, $matrix);
    }

    private function processWordArgument()
    {
        $word = $this->input->getArgument(self::ARG_WORD)
            ?: $this->io->ask(
                sprintf('Enter the resulting "%s":', ucfirst(self::ARG_WORD))
            );

        $this->validateWord($this->filled($word));
        self::CASE_SENSITIVE ?: $word = strtoupper($word);

        $this->input->setArgument(self::ARG_WORD, $word);
    }

    private function resetArgument(string $argument)
    {
        $this->input->setArgument($argument, null);
        $fooName = "process" . ucfirst($argument) . "Argument";
        $this->$fooName();
    }

    private function validateMatrix(string $matrix)
    {
        $isValid = true;

        if (!ctype_alpha($matrix)) {
            $isValid = false;
            $this->io->error(sprintf('"%s" must contain only letters!', ucfirst(self::ARG_MATRIX)));
        } else {
            $sqrt = sqrt(strlen($matrix));
            if (floor($sqrt) != $sqrt) {
                $isValid = false;
                $this->io->error(sprintf('"%s" has wrong characters number!', ucfirst(self::ARG_MATRIX)));
                $this->io->info("Must be a string of size N^2, that describes square matrix of characters N*N");
            } else {
                $this->wordLength = $sqrt;
            }
        }

        if (!$isValid) $this->resetArgument(self::ARG_MATRIX);
    }

    private function validateWord(string $word)
    {
        $isValid = true;

        if (!ctype_alpha($word)) {
            $isValid = false;
            $this->io->error(sprintf('"%s" must contain only letters!', ucfirst(self::ARG_WORD)));
        } elseif (strlen($word) != $this->wordLength) {
            $isValid = false;
            $this->io->error(sprintf('Improper "%s" length!', ucfirst(self::ARG_WORD)));
            $this->io->note(sprintf('Must be equal to the square root of the "%s" length', self::ARG_MATRIX));
        }

        if (!$isValid) $this->resetArgument(self::ARG_WORD);
    }

    private function filled(?string $string): string
    {
        if (empty($string)) {
            $this->io->error('Undefined argument! Aborting...' . PHP_EOL);
            exit();
        }

        return $string;
    }

    private function glue(string $xKey, string $yKey): string
    {
        return $xKey . self::KEYS_SEPARATOR . $yKey;
    }
}