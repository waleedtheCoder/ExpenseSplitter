<?php

namespace App\Services;

use App\Models\Group;

class BalanceService
{
    /**
     * Net balance per user in the group, in integer cents.
     * Positive = the group owes this user money. Negative = this user owes the group.
     *
     * @return array<int, int> user_id => balance in cents
     */
    public function balances(Group $group): array
    {
        $balances = [];

        foreach ($group->members as $member) {
            $balances[$member->id] = 0;
        }

        foreach ($group->expenses as $expense) {
            $balances[$expense->paid_by] = ($balances[$expense->paid_by] ?? 0) + $this->toCents($expense->amount);

            foreach ($expense->shares as $share) {
                $balances[$share->user_id] = ($balances[$share->user_id] ?? 0) - $this->toCents($share->amount);
            }
        }

        foreach ($group->settlements as $settlement) {
            $cents = $this->toCents($settlement->amount);
            $balances[$settlement->from_user_id] = ($balances[$settlement->from_user_id] ?? 0) + $cents;
            $balances[$settlement->to_user_id] = ($balances[$settlement->to_user_id] ?? 0) - $cents;
        }

        return $balances;
    }

    /**
     * Reduce a set of net balances to the minimum number of payments that settle them.
     * Classic greedy algorithm: always match the largest debtor against the largest creditor.
     *
     * @param  array<int, int>  $balances  user_id => balance in cents
     * @return list<array{from: int, to: int, amount: int}> amounts in cents
     */
    public function simplify(array $balances): array
    {
        $debtors = [];
        $creditors = [];

        foreach ($balances as $userId => $cents) {
            if ($cents < 0) {
                $debtors[] = [$userId, -$cents];
            } elseif ($cents > 0) {
                $creditors[] = [$userId, $cents];
            }
        }

        $transfers = [];

        while ($debtors !== [] && $creditors !== []) {
            usort($debtors, fn ($a, $b) => $b[1] <=> $a[1]);
            usort($creditors, fn ($a, $b) => $b[1] <=> $a[1]);

            [$debtorId, $owed] = array_shift($debtors);
            [$creditorId, $due] = array_shift($creditors);

            $amount = min($owed, $due);

            $transfers[] = ['from' => $debtorId, 'to' => $creditorId, 'amount' => $amount];

            $owed -= $amount;
            $due -= $amount;

            if ($owed > 0) {
                $debtors[] = [$debtorId, $owed];
            }

            if ($due > 0) {
                $creditors[] = [$creditorId, $due];
            }
        }

        return $transfers;
    }

    private function toCents(string|float|int $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
