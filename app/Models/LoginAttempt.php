<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class LoginAttempt extends Model
{
    public function lockoutSeconds(string $username): int
    {
        $stmt = $this->db->prepare('SELECT locked_until FROM login_attempts WHERE attempt_key = ? LIMIT 1');
        $stmt->execute([$this->attemptKey($username)]);
        $lockedUntil = $stmt->fetchColumn();

        return $lockedUntil === false ? 0 : max(0, (int) $lockedUntil - time());
    }

    public function recordFailure(string $username): int
    {
        $key = $this->attemptKey($username);
        $this->db->beginTransaction();

        try {
            $insert = $this->db->prepare('INSERT IGNORE INTO login_attempts (attempt_key, failed_attempts, lockout_level, locked_until) VALUES (?, 0, 0, 0)');
            $insert->execute([$key]);

            $select = $this->db->prepare('SELECT failed_attempts, lockout_level, locked_until FROM login_attempts WHERE attempt_key = ? FOR UPDATE');
            $select->execute([$key]);
            $state = $select->fetch();
            $now = time();

            if ((int) $state['locked_until'] > $now) {
                $this->db->commit();
                return (int) $state['locked_until'] - $now;
            }

            $failedAttempts = (int) $state['failed_attempts'] + 1;
            $lockoutLevel = (int) $state['lockout_level'];
            $lockedUntil = 0;
            if ($failedAttempts >= 3) {
                $lockoutSeconds = min(180 * (2 ** min($lockoutLevel, 9)), 86400);
                $lockedUntil = $now + $lockoutSeconds;
                $lockoutLevel = min($lockoutLevel + 1, 10);
                $failedAttempts = 0;
            }

            $update = $this->db->prepare('UPDATE login_attempts SET failed_attempts = ?, lockout_level = ?, locked_until = ?, updated_at = CURRENT_TIMESTAMP WHERE attempt_key = ?');
            $update->execute([$failedAttempts, $lockoutLevel, $lockedUntil, $key]);
            $this->db->commit();

            return $lockedUntil > 0 ? $lockedUntil - $now : 0;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function clear(string $username): void
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE attempt_key = ?');
        $stmt->execute([$this->attemptKey($username)]);
    }

    private function attemptKey(string $username): string
    {
        return hash('sha256', strtolower(trim($username)));
    }
}
