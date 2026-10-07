<?php

namespace App\Domain\Booking;

/**
 * How a visit occupies a professional's time, independent of when it
 * starts: the busy stretches (offsets in minutes from the start) and the
 * total length. Services run one after another; during a service's idle
 * (processing) time the professional is free for someone else.
 */
final readonly class BookingShape
{
    private const int SECONDS_PER_MINUTE = 60;

    /**
     * @param  list<array{int, int}>  $busyOffsets  [from, to) in minutes, sorted, never touching
     */
    private function __construct(public array $busyOffsets, public int $totalMinutes) {}

    /**
     * @param  list<array{active: int, idle: int, finish: int}>  $services  minutes of each service, in order
     */
    public static function fromServices(array $services): self
    {
        $offsets = [];
        $cursor = 0;

        foreach ($services as $service) {
            $offsets = self::push($offsets, $cursor, $cursor + $service['active']);
            $cursor += $service['active'] + $service['idle'];

            if ($service['finish'] > 0) {
                $offsets = self::push($offsets, $cursor, $cursor + $service['finish']);
                $cursor += $service['finish'];
            }
        }

        return new self($offsets, $cursor);
    }

    /**
     * The busy stretches of a visit that starts at the given instant.
     *
     * @return list<TimeSpan>
     */
    public function busyAt(int $start): array
    {
        return array_map(
            fn (array $offset) => new TimeSpan(
                $start + $offset[0] * self::SECONDS_PER_MINUTE,
                $start + $offset[1] * self::SECONDS_PER_MINUTE,
            ),
            $this->busyOffsets,
        );
    }

    public function endsAt(int $start): int
    {
        return $start + $this->totalMinutes * self::SECONDS_PER_MINUTE;
    }

    /**
     * Minutes of the first busy stretch: a start is only worth trying if
     * at least this fits before the working window closes.
     */
    public function firstBusyMinutes(): int
    {
        return $this->busyOffsets === [] ? 0 : $this->busyOffsets[0][1] - $this->busyOffsets[0][0];
    }

    /**
     * Add a stretch, joining it to the previous one when they touch (a
     * service with no idle time, or two services back to back).
     *
     * @param  list<array{int, int}>  $offsets
     * @return list<array{int, int}>
     */
    private static function push(array $offsets, int $from, int $to): array
    {
        if ($to <= $from) {
            return $offsets;
        }

        $last = count($offsets) - 1;

        if ($last >= 0 && $offsets[$last][1] === $from) {
            $offsets[$last] = [$offsets[$last][0], $to];

            return $offsets;
        }

        $offsets[] = [$from, $to];

        return $offsets;
    }
}
