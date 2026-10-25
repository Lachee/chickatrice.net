import type { Pool } from 'mysql2/promise';

/**
 * Runs `fn` while holding the MySQL named lock `name`, so only one instance of
 * the app does it at a time. Named locks belong to a connection, so one is
 * taken out of the pool and held for the duration; `fn` itself is free to use
 * the pool as normal.
 *
 * `waitSeconds` is how long to queue for the lock: 0 skips straight away when
 * another instance holds it, which is what returning `false` reports.
 */
export async function withLock(pool: Pool, name: string, waitSeconds: number, fn: () => Promise<void>): Promise<boolean> {
  const connection = await pool.getConnection();
  try {
    const [ rows ] = await connection.query('SELECT GET_LOCK(?, ?) AS got', [ name, waitSeconds ]);
    if ((rows as { got: number | null }[])[0]?.got !== 1)
      return false;

    try {
      await fn();
    } finally {
      await connection.query('SELECT RELEASE_LOCK(?)', [ name ]);
    }
    return true;
  } finally {
    connection.release();
  }
}
