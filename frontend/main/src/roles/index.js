import { hrModule } from './hr';
import { supervisorModule } from "./supervisor/index.jsx"
import { traineeModule } from "./trainee/index.jsx"

export const ROLES = {
  HR: 'HR',
  SUPERVISOR: 'SUPERVISOR',
  TRAINEE: 'TRAINEE',
};

/**
 * سجلّ الأدوار — نقطة الإضافة الوحيدة لأي دور جديد.
 *
 * لإضافة دور المشرف لاحقاً:
 *   1) أنشئ src/roles/supervisor/index.jsx  (نفس شكل hrModule مع role: 'SUPERVISOR' و basePath: '/supervisor')
 *   2) أنشئ ملفات الترجمة src/i18n/locales/{ar,en}/supervisor.json
 *   3) أضف السطر التالي هنا:  [ROLES.SUPERVISOR]: supervisorModule,
 * ولا حاجة لتعديل الراوتر أو الشريط الجانبي أو صفحة الدخول.
 */
export const roleRegistry = {
  [ROLES.HR]: hrModule,
  [ROLES.SUPERVISOR]: supervisorModule,
  [ROLES.TRAINEE]: traineeModule,
};

export const loginRoles = Object.values(ROLES).map((role) => ({
  value: role,
  enabled: Boolean(roleRegistry[role]),
}));
