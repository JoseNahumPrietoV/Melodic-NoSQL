const database = process.env.MONGO_INITDB_DATABASE;
const admin = db.getSiblingDB('admin');
const mechanisms = ['SCRAM-SHA-256'];

const users = [
    { user: 'melodic_app', pwd: process.env.MONGO_APP_PASSWORD, roles: [{ role: 'readWrite', db: database }] },
    { user: 'melodic_readonly', pwd: process.env.MONGO_READONLY_PASSWORD, roles: [{ role: 'read', db: database }] },
    { user: 'melodic_backup', pwd: process.env.MONGO_BACKUP_PASSWORD, roles: [{ role: 'backup', db: 'admin' }] },
    { user: 'melodic_test', pwd: process.env.MONGO_TEST_PASSWORD, roles: [{ role: 'readWrite', db: `${database}_testing` }] },
];

for (const { user, pwd, roles } of users.filter(({ pwd }) => pwd)) {
    if (admin.getUser(user)) {
        admin.updateUser(user, { pwd, roles, mechanisms });
    } else {
        admin.createUser({ user, pwd, roles, mechanisms });
    }
}
