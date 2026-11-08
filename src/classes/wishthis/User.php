<?php

/**
 * A wishthis user.
 *
 * @author Jay Trees <github.jay@grandel.anonaddy.me>
 */

namespace wishthis;

class User
{
    /**
     * How many days after a birthday fulfilled wishes leave the lists.
     */
    public const RECEIVED_DAYS_AFTER_BIRTHDAY = 14;

    /**
     * After Christmas, fulfilled wishes leave the lists at the end of January.
     */
    public const RECEIVED_AFTER_CHRISTMAS_UNTIL = '01-31';

    /**
     * If the next birthday or Christmas is closer than this, fulfilled wishes
     * wait for it too. They may have been bought for it.
     */
    public const RECEIVED_WAIT_FOR_NEXT = '+2 months';

    public static function getFromID(int $user_id): self
    {
        global $database;

        $userQuery = $database
        ->query(
            'SELECT *
               FROM `users`
              WHERE `id` = :user_id',
            [
                'user_id' => $user_id,
            ]
        );

        if (false !== $userQuery) {
            $fields = $userQuery->fetch();
            $user   = new User($fields);

            return $user;
        }

        throw new \Exception('Unable to find user with ID ' . $user_id . '. Does it exist?');
    }

    public static function passwordToHash(string $plainPassword): string
    {
        return \password_hash($plainPassword, \PASSWORD_BCRYPT);
    }

    public static function passwordToOldHash(string $plainPassword): string
    {
        return \sha1($plainPassword);
    }


    public static function getCurrent(): self
    {
        if (!isset($_SESSION['user'])) {
            $_SESSION['user'] = new self();
        }

        return $_SESSION['user'];
    }

    /**
     * The users unique ID.
     *
     * @var int
     */
    private int $id;

    /**
     * The users unique email address. They are not verified and may be made
     * up.
     *
     * @var string
     */
    private string $email;

    /**
     * The users hashed password.
     *
     * @var string
     */
    private string $password;

    /**
     * The users password reset token.
     *
     * @var string
     */
    private string $password_reset_token;

    /**
     * A unix timestamp indicating until when the users password reset token is
     * valid.
     *
     * @var int
     */
    private int $password_reset_valid_until;

    /**
     * A unix timestamp of when the user has logged in last.
     *
     * @var int
     */
    private int $last_login;

    /**
     * The users power. Administrator have 100, users 1 and unregistered guests
     * 0.
     *
     * @var int
     */
    private int $power = 0;

    /**
     * The users birthdate, formatted as `YYYY-MM-DD`.
     *
     * @var string
     */
    private string $birthdate;

    /**
     * More accurately, this is the users locale (e. g. `en_GB`).
     *
     * @var string
     */
    private string $language;

    /**
     * The users currency (e. g. `EUR`).
     *
     * @var string
     */
    private string $currency;

    /**
     * The users first name.
     *
     * @var string
     */
    private string $name_first;

    /**
     * The users last name.
     *
     * @var string
     */
    private string $name_last;

    /**
     * The users nick name.
     *
     * @var string
     */
    private string $name_nick;

    /**
     * The users preferred release channel. Usually `stable` or
     * `release-candidate` but can also be unset if not \defined.
     *
     * @var string
     */
    private string $channel;

    /**
     * Whether the user consented to seeing advertisements.
     *
     * @var bool
     */
    private bool $advertisements = false;

    /**
     * Whether fulfilled wishes should leave the users lists two weeks after
     * their birthday. They are kept under Spoilers as received.
     *
     * @var bool
     */
    private bool $removeReceivedWishes = false;

    /**
     * Whether the user wants to stay logged in.
     *
     * @var bool
     */
    private bool $stayLoggedIn = false;

    /**
     * Non-Static
     */
    public ?\Gettext\Translations $translations = null;

    public function __construct(array $fields = [])
    {
        if (!empty($fields)) {
            $this->id                         = $fields['id'];
            $this->email                      = $fields['email'];
            $this->password                   = $fields['password'];
            $this->password_reset_token       = $fields['password_reset_token'] ?? '';
            $this->password_reset_valid_until = \strtotime($fields['password_reset_valid_until']);
            $this->last_login                 = \strtotime($fields['last_login']);
            $this->power                      = $fields['power'];
            $this->birthdate                  = $fields['birthdate'] ?? '';
            $this->language                   = $fields['language'];
            $this->currency                   = $fields['currency'];
            $this->name_first                 = $fields['name_first'] ?? '';
            $this->name_last                  = $fields['name_last'] ?? '';
            $this->name_nick                  = $fields['name_nick'] ?? '';
            $this->channel                    = $fields['channel'] ?? '';
            $this->advertisements             = $fields['advertisements'];
            $this->removeReceivedWishes       = (bool) ($fields['remove_received_wishes'] ?? false);
        }

        /** Set Language */
        if (!isset($this->language)) {
            $locale = DEFAULT_LOCALE;

            if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $locale = \Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']) ?: DEFAULT_LOCALE;
            }

            $this->language = $locale;
        }

        $this->setLocale($this->language);
    }

    /**
     * Set the users locale
     *
     * @param string $locale
     *
     * @return void
     */
    public function setLocale(string $locale): void
    {
        /** Load Translation */
        $translationFilepath = ROOT . '/translations/' . $locale . '.po';

        if (\file_exists($translationFilepath)) {
            $loader             = new \Gettext\Loader\PoLoader();
            $this->translations = $loader->loadFile($translationFilepath);
        } else {
            \trigger_error('Unable to find translations for ' . $locale . ', defaulting to ' . DEFAULT_LOCALE . '.', \E_USER_NOTICE);
        }

        /** Set locale */
        $this->language = $locale;
    }

    public function getLocale(): string
    {
        return $this->language ?? DEFAULT_LOCALE;
    }

    /**
     * Set the user currency
     *
     * @param string $locale
     *
     * @return void
     */
    public function setCurrency(string $locale): void
    {
        $this->currency = $locale;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Return whether the current user is logged in.
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        if (!isset($_COOKIE['wishthis'], $_COOKIE['wishthis_session'])) {
            return false;
        }

        global $database;

        if (false === $database) {
            return false;
        }

        $database->connect();

        $sessions = $database
        ->query(
            'SELECT *
               FROM `sessions`
              WHERE `session` = :session',
            [
                'session' => $_COOKIE['wishthis_session'],
            ]
        )
        ->fetchAll(\PDO::FETCH_ASSOC);

        if (false === $sessions) {
            return false;
        }

        foreach ($sessions as $session) {
            if (\strtotime($session['expires']) > \time()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a list of the users wishlists.
     *
     * @return array
     */
    public function getWishlists(): array
    {
        global $database;

        $wishlists = $database
        ->query(
            'SELECT *
               FROM `wishlists`
              WHERE `user` = :user_id;',
            [
                'user_id' => $this->id,
            ]
        )
        ->fetchAll(\PDO::FETCH_ASSOC);

        return $wishlists;
    }

    /**
     * Returns whether the user is logged in and owns the specified wishlist.
     *
     * @param int $wishlistId
     *
     * @return bool
     */
    public function ownsWishlist(int $wishlistId): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $wishlist = Wishlist::getFromId($wishlistId);

        return false !== $wishlist && $wishlist->getUserId() === $this->id;
    }

    public function getSavedWishlists(): array
    {
        global $database;

        $wishlists = [];

        if (!$this->isLoggedIn()) {
            return $wishlists;
        }

        $result = $database
        ->query(
            '  SELECT `wishlists_saved`.`wishlist`,
                      `wishlists`.`user`,
                      `wishlists`.`hash`
                 FROM `wishlists_saved`
            LEFT JOIN `wishlists` ON `wishlists`.`id` = `wishlists_saved`.`wishlist`
                WHERE `wishlists_saved`.`user` = :user_id;',
            [
                'user_id' => $this->id,
            ]
        )
        ->fetchAll(\PDO::FETCH_ASSOC);

        if ($result) {
            $wishlists = $result;
        }

        return $wishlists;
    }

    public function getDisplayName(): string
    {
        return $this->name_nick
            ?: $this->name_first
            ?: $this->email;
    }

    public function updatePasswordHash(string $password, string $email): string
    {
        global $database;
        $passwordHash = User::passwordToHash($password);

        $database->query(
            'UPDATE `users`
                SET `password` = :password_hash
            WHERE `email` = :user_email',
            [
                "user_email"    => $email,
                "password_hash" => $passwordHash,
            ]
        );
        return $passwordHash;
    }

    /**
     * Attempts to log in the user. Return whether it was successful or not.
     *
     * @return bool Whether the log in was successful.
     */
    public function logIn(string $email = '', string $password = '', bool $userLoginIsPersistent = false): bool
    {
        $database = new Database(
            DATABASE_ENGINE,
            DATABASE_HOST,
            DATABASE_NAME,
            DATABASE_USER,
            DATABASE_PASSWORD
        );
        $database->connect();

        $login_was_successful = false;

        if ('' === $email && '' === $password && isset($this->email, $this->password)) {
            $email    = $this->email;
            $password = $this->password;
        }

        /**
         * Attempt to fetch the user.
         */
        $user_database_fields = $database
        ->query(
            'SELECT *
               FROM `users`
              WHERE `email`      = :user_email',
            [
                'user_email' => $email,
            ]
        )
        ->fetch(\PDO::FETCH_ASSOC);

        /**
         * Fetching the user fields has failed and we are now assuming that the
         * credentials are wrong or that the user does not exist.
         */
        if (false === $user_database_fields) {
            return false;
        }

        $passwordHash = User::passwordToOldHash($password);

        if ($passwordHash === $user_database_fields["password"]) {
            $passwordHash = User::updatePasswordHash($password, $email);
        } else {
            $passwordHash = $user_database_fields["password"];
        }

        $password_matches = password_verify($password, $passwordHash);

        if (!$password_matches) {
            return false;
        }

        /**
         * Update the `last_login` column.
         */
        $database
        ->query(
            'UPDATE `users`
                SET `last_login` = ' . $database->getDateNow() . '
              WHERE `email`      = :user_email',
            [
                'user_email' => $email,
            ]
        );
        $user_database_fields['last_login'] = \date('Y-m-d H:i');

        /**
         * Set session duration
         */
        $this->refreshSession($user_database_fields['id']);

        /**
         * Create a `User` object instance and assign it for later use.
         */
        if (\is_array($user_database_fields)) {
            $this->__construct($user_database_fields);
            $this->stayLoggedIn = $userLoginIsPersistent;

            $_SESSION['user'] = $this;

            /** Keep the gifts made before logging in. */
            Gift::claimSessionGifts($this->id);

            $login_was_successful = true;
        }

        return $login_was_successful;
    }

    public function logOut(): void
    {
        /** Destroy session */
        $database = new Database(
            DATABASE_ENGINE,
            DATABASE_HOST,
            DATABASE_NAME,
            DATABASE_USER,
            DATABASE_PASSWORD
        );
        $database->connect();
        $database
        ->query(
            'DELETE FROM `sessions`
                   WHERE `session` = :session',
            [
                'session' => $_COOKIE['wishthis_session'] ?? '',
            ]
        );

        \session_destroy();
        unset($_SESSION);

        /** Delete cookie */
        \setcookie('wishthis_session', '', \time() - 3600, '/');
        unset($_COOKIE['wishthis_session']);
    }

    public function delete(): void
    {
        global $database;

        $database->query(
            'DELETE FROM `users`
                   WHERE `id` = :user_id',
            [
                'user_id' => $this->id,
            ]
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNameFirst(): string
    {
        return $this->name_first;
    }

    public function setNameFirst(string $nameFirst): void
    {
        $this->name_first = $nameFirst;
    }

    public function getNameLast(): string
    {
        return $this->name_last;
    }

    public function setNameLast(string $nameLast): void
    {
        $this->name_last = $nameLast;
    }

    public function getNameNick(): string
    {
        return $this->name_nick;
    }

    public function setNameNick(string $nameNick): void
    {
        $this->name_nick = $nameNick;
    }

    public function getEmail(): string
    {
        return $this->email ?? '';
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getPower(): int
    {
        return $this->power;
    }

    public function getBirthdate(): string
    {
        return $this->birthdate;
    }

    public function setBirthdate(string $birthdate): void
    {
        $this->birthdate = $birthdate;
    }

    public function getChannel(): string|null
    {
        return $this->channel ?? null;
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

    public function getAdvertisements(): bool
    {
        return $this->advertisements;
    }

    public function setAdvertisements(bool $advertisements): void
    {
        $this->advertisements = $advertisements;
    }

    public function getRemoveReceivedWishes(): bool
    {
        return $this->removeReceivedWishes;
    }

    public function setRemoveReceivedWishes(bool $removeReceivedWishes): void
    {
        $this->removeReceivedWishes = $removeReceivedWishes;
    }

    /**
     * Returns the day on which a fulfilled wish leaves its owners lists.
     *
     * That is once the next birthday or Christmas after it was fulfilled is
     * over: two weeks after the birthday, or at the end of January after
     * Christmas. If the next of these is less than two months away by then,
     * the wish may have been bought for that one, so it waits for it too.
     *
     * A birthday on the 29th of February falls on the 1st of March in other
     * years.
     *
     * @param \DateTimeImmutable $fulfilled When the wish was fulfilled.
     * @param string             $birthdate Formatted as `YYYY-MM-DD`.
     *
     * @return \DateTimeImmutable|null The day (at midnight), or `null` if
     *                                 `$birthdate` is not a valid date.
     */
    public static function getReceivedDate(\DateTimeImmutable $fulfilled, string $birthdate): ?\DateTimeImmutable
    {
        if (1 !== \preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $birthdate, $matches)) {
            return null;
        }

        $month = (int) $matches[1];
        $day   = (int) $matches[2];

        if (!\checkdate($month, $day, 2000)) {
            return null;
        }

        $fulfilled = $fulfilled->setTime(0, 0);
        $year      = (int) $fulfilled->format('Y');

        [$untilMonth, $untilDay] = \array_map('intval', \explode('-', self::RECEIVED_AFTER_CHRISTMAS_UNTIL));

        /** The occasions from the year it was fulfilled on, and when they are over. */
        $occasions = [];

        for ($occasionYear = $year; $occasionYear <= $year + 2; $occasionYear++) {
            $birthday    = $fulfilled->setDate($occasionYear, $month, $day);
            $occasions[] = [
                'date' => $birthday,
                'over' => $birthday->modify('+' . self::RECEIVED_DAYS_AFTER_BIRTHDAY . ' days'),
            ];

            $occasions[] = [
                'date' => $fulfilled->setDate($occasionYear, 12, 25),
                'over' => $fulfilled->setDate($occasionYear + 1, $untilMonth, $untilDay),
            ];
        }

        \usort(
            $occasions,
            function (array $a, array $b) {
                return $a['date'] <=> $b['date'];
            }
        );

        $receivedDate = null;

        foreach ($occasions as $occasion) {
            if ($occasion['date'] < $fulfilled) {
                continue;
            }

            /** The first occasion after the wish was fulfilled. */
            if (null === $receivedDate) {
                $receivedDate = $occasion['over'];

                continue;
            }

            /** Wait for the next occasion too, if it is close. */
            if ($occasion['date'] <= $receivedDate->modify(self::RECEIVED_WAIT_FOR_NEXT)) {
                $receivedDate = \max($receivedDate, $occasion['over']);

                continue;
            }

            break;
        }

        return $receivedDate;
    }

    /**
     * Takes fulfilled wishes off the users lists once the birthday or
     * Christmas they were likely bought for is over, by marking them as
     * received. They are still shown under Spoilers.
     *
     * Does nothing if the user turned this off or has no birthday set. The
     * caller checks that the user is logged in.
     *
     * @see self::getReceivedDate()
     */
    public function markReceivedWishes(): void
    {
        global $database;

        if (!isset($this->id) || !$this->removeReceivedWishes || '' === $this->birthdate) {
            return;
        }

        $wishesQuery = $database->query(
            'SELECT `id`, `edited`
               FROM `wishes`
              WHERE `received` IS NULL
                AND `status` IN (:status_fulfilled, :status_unavailable)
                AND `wishlist` IN (
                    SELECT `id`
                      FROM `wishlists`
                     WHERE `user` = :user_id
                )',
            [
                'status_fulfilled'   => Wish::STATUS_FULFILLED,
                'status_unavailable' => Wish::STATUS_UNAVAILABLE,
                'user_id'            => $this->id,
            ]
        );

        $today   = new \DateTimeImmutable('today');
        $wishIds  = [];

        foreach ($wishesQuery ? $wishesQuery->fetchAll(\PDO::FETCH_ASSOC) : [] as $wish) {
            /** `edited` is when the wish was fulfilled, unless it was changed since. */
            $receivedDate = self::getReceivedDate(new \DateTimeImmutable($wish['edited']), $this->birthdate);

            if (null !== $receivedDate && $receivedDate <= $today) {
                $wishIds[] = (int) $wish['id'];
            }
        }

        if (empty($wishIds)) {
            return;
        }

        /**
         * `edited` is set to itself, so MySQL does not update it. Otherwise
         * these wishes would show up as recent changes to undo.
         */
        $database->query(
            'UPDATE `wishes`
                SET `received` = ' . $database->getDateNow() . ',
                    `edited`   = `edited`
              WHERE `id` IN (' . \implode(', ', $wishIds) . ')'
        );
    }

    public function getPasswordResetValidUntil(): int
    {
        return $this->password_reset_valid_until;
    }

    public function refreshSession(int $forUser = 0): void
    {
        $sessionId              = $_COOKIE['wishthis_session']
                               ?? \password_hash(\bin2hex(\random_bytes(32)), \PASSWORD_BCRYPT);
        $sessionDurationSeconds = 1440;

        if ($this->stayLoggedIn) {
            $sessionDurationSeconds = 7776000; /** Three months */
        }

        $sessionExpires = \time() + $sessionDurationSeconds;

        if (0 === $forUser) {
            $forUser = $this->id;
        }

        $database = new Database(
            DATABASE_ENGINE,
            DATABASE_HOST,
            DATABASE_NAME,
            DATABASE_USER,
            DATABASE_PASSWORD
        );
        $database->connect();

        /** Create cookie */
        \setcookie('wishthis_session', $sessionId, $sessionExpires, '/');
        $_COOKIE['wishthis_session'] = $sessionId;

        /** Delete outdated sessions */
        $database
        ->query(
            'DELETE FROM `sessions`
                   WHERE `expires` <= ' . $database->getDateNow()
        );

        /** Find existing session */
        $sessionsExisting = $database
        ->query(
            'SELECT *
               FROM `sessions`
              WHERE `session` = :session',
            [
                'session' => $sessionId,
            ]
        )
        ->fetchAll();

        /** The session exists and can be updated now */
        foreach ($sessionsExisting as $session) {
            if ($session['session'] === $sessionId) {
                $database
                ->query(
                    'UPDATE `sessions`
                        SET `expires` = :expires
                      WHERE `session` = :session
                        AND `user` = :user',
                    [
                        'expires' => \date('Y-m-d H:i', $sessionExpires),
                        'session' => $sessionId,
                        'user'    => $forUser,
                    ]
                );

                /** There's no need to do anything further. */
                return;
            }
        }

        /**
         * Since there has been no return until now, we are assuming the session
         * does not exist and will create it now.
         */
        $database
        ->query(
            'INSERT INTO `sessions` (`user`, `session`, `expires`) VALUES (
                :user_id,
                :session_id,
                :session_expires
            )',
            [
                'user_id'         => $forUser,
                'session_id'      => $sessionId,
                'session_expires' => \date('Y-m-d H:i', \time() + $sessionDurationSeconds),
            ]
        );
    }

    public function loadFromSession(): void
    {
        if (!isset($_COOKIE['wishthis_session'])) {
            return;
        }

        $database = new Database(
            DATABASE_ENGINE,
            DATABASE_HOST,
            DATABASE_NAME,
            DATABASE_USER,
            DATABASE_PASSWORD
        );
        $database->connect();

        $session = $database
        ->query(
            'SELECT *
               FROM `sessions`
              WHERE `session` = :session',
            [
                'session' => $_COOKIE['wishthis_session'],
            ]
        )
        ->fetch(\PDO::FETCH_ASSOC);

        if (false === $session) {
            return;
        }

        $user = $database
        ->query(
            'SELECT *
               FROM `users`
              WHERE `id` = :user',
            [
                'user' => $session['user'],
            ]
        )
        ->fetch(\PDO::FETCH_ASSOC);

        if (false === $user) {
            return;
        }

        $this->__construct($user);
        $this->stayLoggedIn = true;
    }
}
