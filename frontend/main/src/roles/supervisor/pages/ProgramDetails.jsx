import { useTranslation } from "react-i18next";
import { Plus, ClipboardList, ArrowRight, CalendarDays } from "lucide-react";
import styles from "./ProgramDetails.module.css";
import useAssigned from "../../../hooks/SupervisorHooks/useassigned";
import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import Loading from "../../../components/ui/Loading";
import CreateTaskModal from "../components/CreateTaskModal";
import TaskCard from "../components/TaskCard";
export default function ProgramDetails({ onAddTask, onTaskClick }) {
  const { t } = useTranslation("common");
  
  const { programId } = useParams();
  const navigate = useNavigate();

  const handleViewTask = (taskId) => {
    navigate(`/supervisor/tasks/details/${taskId}`);
  };
  const { error, loading, loadingAction, getProgram, program } = useAssigned();
  const [showCreateTask, setShowCreateTask] = useState(false);
  const [phase, setPhase] = useState("");

  const handelShowCreateTask = (phase) => {
    console.log("phase", phase);
    setShowCreateTask(true);
    setPhase(phase);
  };

  console.log(program);
  const phases = program.phases || [];

  useEffect(() => {
    getProgram(programId);
  }, []);

  return loadingAction ? (
    <Loading />
  ) : (
    <>
      <main className={styles.page} dir="auto">
        {/* العودة إلى صفحة البرامج */}
        <button
          className={styles.backButton}
          onClick={() => window.history.back()}
        >
          <ArrowRight size={18} />
          {t("program.backToPrograms")}
        </button>

        {/* معلومات البرنامج */}
        <header className={styles.programHeader}>
          <div className={styles.programIcon}>
            <ClipboardList size={28} />
          </div>

          <div className={styles.programInfo}>
            <h1>{program.title || program.name}</h1>
            <p>{program.description}</p>

            {program.deadline && (
              <div className={styles.deadline}>
                <CalendarDays size={16} />
                <span>
                  {t("program.deadline")}: {program.deadline}
                </span>
              </div>
            )}
          </div>
        </header>

        {/* المراحل والمهام */}
        <section className={styles.phases}>
          <div className={styles.sectionHeader}>
            <h2>{t("program.phasesTitle")}</h2>
            <span className={styles.count}>{phases.length}</span>
          </div>

          {phases.length === 0 ? (
            <div className={styles.empty}>{t("program.noPhases")}</div>
          ) : (
            phases.map((phase, index) => (
              <article
                className={styles.phase}
                key={phase._id || phase.id || index}
              >
                {/* معلومات المرحلة */}
                <div className={styles.phaseHeader}>
                  <div className={styles.phaseInfo}>
                    <span className={styles.phaseNumber}>{index + 1}</span>

                    <div>
                      <h3>{phase.name}</h3>
                      <p>{phase.description || t("program.noDescription")}</p>
                    </div>
                  </div>

                  <button
                    className={styles.addButton}
                    onClick={() => handelShowCreateTask(phase)}
                  >
                    <Plus size={18} />
                    {t("task.add")}
                  </button>
                </div>

                {/* مهام المرحلة */}
                <div className={styles.tasks}>
                  {(phase.tasks || []).length === 0 ? (
                    <p className={styles.noTasks}>{t("task.noTasks")}</p>
                  ) : (
                    phase.tasks.map((task) => (
                    
                      <TaskCard task={task} key={task.id} />
                    ))
                  )}
                </div>
              </article>
            ))
          )}
        </section>
      </main>
      {showCreateTask && (
        <CreateTaskModal
          onClose={() => setShowCreateTask(false)}
          phase={phase}
          onUpdated={()=>getProgram(programId,true)}
        />
      )}
    </>
  );
}

// const program = {
//   _id: "program-001",
//   title: "Full Stack Web Development",
//   description:
//     "برنامج تدريبي متكامل لتأهيل المتدربين على تطوير تطبيقات الويب باستخدام React.js وNode.js وNestJS وقواعد البيانات، مع تطبيق أفضل الممارسات في كتابة الكود والاختبار والنشر.",

//   startDate: "2026-09-01",
//   deadline: "2026-12-30",

//   status: "IN_PROGRESS",
//   supervisor: {
//     _id: "supervisor-001",
//     fullname: "Ahmad Al-Hassan",
//     email: "ahmad.supervisor@syriatel.test",
//   },

//   traineesCount: 4,

//   phases: [
//     {
//       _id: "phase-001",
//       name: "Frontend Development",
//       description:
//         "تعلم بناء واجهات مستخدم تفاعلية ومتجاوبة باستخدام React.js، مع التركيز على تنظيم المكونات وإدارة الحالة والتعامل مع APIs.",

//       startDate: "2026-09-01",
//       deadline: "2026-09-25",
//       status: "COMPLETED",
//       progress: 100,

//       tasks: [
//         {
//           _id: "task-001",
//           title: "Build Reusable React Components",
//           description:
//             "إنشاء مجموعة من مكونات React القابلة لإعادة الاستخدام، بما في ذلك Button وInput وModal وCard، مع دعم الخصائص المختلفة.",

//           status: "COMPLETED",
//           priority: "HIGH",
//           createdAt: "2026-09-02",
//           deadline: "2026-09-07",
//           completedAt: "2026-09-06",

//           assignee: {
//             _id: "trainee-001",
//             fullname: "Ahmad Mohammad",
//             email: "ahmad.mohammad@syriatel.test",
//           },

//           notes: "تمت مراجعة الكود والموافقة عليه.",
//         },
//         {
//           _id: "task-002",
//           title: "Implement Responsive Layout",
//           description:
//             "تصميم واجهة متجاوبة تعمل على أجهزة الكمبيوتر والأجهزة اللوحية والهواتف المحمولة باستخدام CSS Modules.",

//           status: "COMPLETED",
//           priority: "MEDIUM",
//           createdAt: "2026-09-05",
//           deadline: "2026-09-12",
//           completedAt: "2026-09-11",

//           assignee: {
//             _id: "trainee-002",
//             fullname: "Sara Khaled",
//             email: "sara.khaled@syriatel.test",
//           },

//           notes: "تم اختبار التصميم على أحجام شاشات متعددة.",
//         },
//         {
//           _id: "task-003",
//           title: "Integrate REST API",
//           description:
//             "ربط واجهة React مع REST API باستخدام Axios، ومعالجة حالات التحميل والأخطاء وعرض رسائل مناسبة للمستخدم.",

//           status: "COMPLETED",
//           priority: "HIGH",
//           createdAt: "2026-09-10",
//           deadline: "2026-09-18",
//           completedAt: "2026-09-17",

//           assignee: {
//             _id: "trainee-003",
//             fullname: "Omar Ali",
//             email: "omar.ali@syriatel.test",
//           },

//           notes: "تمت معالجة أخطاء الشبكة والاستجابات غير المتوقعة.",
//         },
//       ],
//     },

//     {
//       _id: "phase-002",
//       name: "Backend Development",
//       description:
//         "تطوير واجهات API آمنة ومنظمة باستخدام Node.js وNestJS، مع تطبيق التحقق من المدخلات ومعالجة الأخطاء وفصل المسؤوليات.",

//       startDate: "2026-09-26",
//       deadline: "2026-10-20",
//       status: "IN_PROGRESS",
//       progress: 50,

//       tasks: [
//         {
//           _id: "task-004",
//           title: "Create Authentication Module",
//           description:
//             "إنشاء نظام تسجيل دخول باستخدام JWT، والتحقق من بيانات المستخدم، وتنظيم منطق المصادقة ضمن وحدة مستقلة.",

//           status: "COMPLETED",
//           priority: "HIGH",
//           createdAt: "2026-09-27",
//           deadline: "2026-10-03",
//           completedAt: "2026-10-02",

//           assignee: {
//             _id: "trainee-001",
//             fullname: "Ahmad Mohammad",
//             email: "ahmad.mohammad@syriatel.test",
//           },

//           notes: "تم اختبار حالات تسجيل الدخول الناجحة والفاشلة.",
//         },
//         {
//           _id: "task-005",
//           title: "Implement Role-Based Authorization",
//           description:
//             "تطبيق صلاحيات الوصول حسب الأدوار، ومنع المستخدمين من الوصول إلى الموارد التي لا يملكون صلاحية إدارتها.",

//           status: "IN_PROGRESS",
//           priority: "HIGH",
//           createdAt: "2026-10-01",
//           deadline: "2026-10-14",
//           completedAt: null,

//           assignee: {
//             _id: "trainee-002",
//             fullname: "Sara Khaled",
//             email: "sara.khaled@syriatel.test",
//           },

//           notes: "يجب اختبار صلاحيات HR والمشرف والمتدرب.",
//         },
//         {
//           _id: "task-006",
//           title: "Build Applicants API",
//           description:
//             "إنشاء عمليات عرض المتقدمين والبحث والتصفية والتقسيم إلى صفحات، مع التحقق من صحة معاملات الطلب.",

//           status: "IN_PROGRESS",
//           priority: "MEDIUM",
//           createdAt: "2026-10-04",
//           deadline: "2026-10-16",
//           completedAt: null,

//           assignee: {
//             _id: "trainee-004",
//             fullname: "Lina Mahmoud",
//             email: "lina.mahmoud@syriatel.test",
//           },

//           notes: "يجب أن تعمل التصفية مع التقسيم إلى صفحات بشكل صحيح.",
//         },
//       ],
//     },

//     {
//       _id: "phase-003",
//       name: "Database Design",
//       description:
//         "تصميم نماذج البيانات والعلاقات والفهارس، وضمان اتساق البيانات وسهولة الاستعلام عنها مع مراعاة متطلبات النظام.",

//       startDate: "2026-10-21",
//       deadline: "2026-11-05",
//       status: "PENDING",
//       progress: 0,

//       tasks: [
//         {
//           _id: "task-007",
//           title: "Design Database Schema",
//           description:
//             "تصميم نماذج المستخدمين والبرامج والمراحل والمهام والتعيينات، وتحديد العلاقات والقيود اللازمة.",

//           status: "PENDING",
//           priority: "HIGH",
//           createdAt: "2026-10-12",
//           deadline: "2026-10-25",
//           completedAt: null,

//           assignee: {
//             _id: "trainee-003",
//             fullname: "Omar Ali",
//             email: "omar.ali@syriatel.test",
//           },

//           notes: "يجب توثيق العلاقات ومبررات اختيار التصميم.",
//         },
//         {
//           _id: "task-008",
//           title: "Optimize Database Queries",
//           description:
//             "تحليل الاستعلامات الأكثر استخدامًا، وإضافة الفهارس المناسبة، وتقييم أثرها على الأداء.",

//           status: "PENDING",
//           priority: "MEDIUM",
//           createdAt: "2026-10-12",
//           deadline: "2026-10-30",
//           completedAt: null,

//           assignee: {
//             _id: "trainee-004",
//             fullname: "Lina Mahmoud",
//             email: "lina.mahmoud@syriatel.test",
//           },

//           notes: "توثيق الاستعلامات قبل التحسين وبعده.",
//         },
//         {
//           _id: "task-009",
//           title: "Implement Data Validation",
//           description:
//             "إضافة قواعد التحقق من البيانات والتعامل مع القيم الناقصة والتكرار والعلاقات غير الصحيحة.",

//           status: "PENDING",
//           priority: "HIGH",
//           createdAt: "2026-10-13",
//           deadline: "2026-11-02",
//           completedAt: null,

//           assignee: {
//             _id: "trainee-001",
//             fullname: "Ahmad Mohammad",
//             email: "ahmad.mohammad@syriatel.test",
//           },

//           notes: "اختبار البيانات الصحيحة وغير الصحيحة.",
//         },
//       ],
//     },

//     {
//       _id: "phase-004",
//       name: "Testing and Quality Assurance",
//       description:
//         "التحقق من صحة التطبيق عبر الاختبارات الوحدوية واختبارات التكامل واختبارات الواجهات، والتأكد من معالجة الحالات الاستثنائية.",

//       startDate: "2026-11-06",
//       deadline: "2026-11-25",
//       status: "PENDING",
//       progress: 0,

//       tasks: [
//         {
//           _id: "task-010",
//           title: "Write Unit Tests",
//           description:
//             "كتابة اختبارات وحدوية للخدمات والمكونات الأساسية، مع تغطية الحالات الناجحة وحالات الخطأ.",

//           status: "PENDING",
//           priority: "HIGH",
//           createdAt: "2026-10-13",
//           deadline: "2026-11-12",
//           completedAt: null,

//           assignee: {
//             _id: "trainee-002",
//             fullname: "Sara Khaled",
//             email: "sara.khaled@syriatel.test",
//           },

//           notes: "تحديد معايير نجاح الاختبارات قبل التنفيذ.",
//         },
//         {
//           _id: "task-011",
//           title: "Test API Endpoints",
//           description:
//             "اختبار واجهات API باستخدام Postman، بما في ذلك التحقق من الصلاحيات والاستجابات وأكواد HTTP.",

//           status: "PENDING",
//           priority: "MEDIUM",
//           createdAt: "2026-10-13",
//           deadline: "2026-11-17",
//           completedAt: null,

//           assignee: {
//             _id: "trainee-003",
//             fullname: "Omar Ali",
//             email: "omar.ali@syriatel.test",
//           },

//           notes: "حفظ حالات الاختبار ونتائج التنفيذ.",
//         },
//         {
//           _id: "task-012",
//           title: "Fix Reported Bugs",
//           description:
//             "تحليل الأخطاء المكتشفة، وتحديد أسبابها، وإصلاحها، ثم إعادة تنفيذ الاختبارات ذات الصلة.",

//           status: "PENDING",
//           priority: "HIGH",
//           createdAt: "2026-10-13",
//           deadline: "2026-11-23",
//           completedAt: null,

//           assignee: {
//             _id: "trainee-004",
//             fullname: "Lina Mahmoud",
//             email: "lina.mahmoud@syriatel.test",
//           },

//           notes: "يجب إرفاق وصف الخطأ وخطوات إعادة إنتاجه.",
//         },
//       ],
//     },

//     {
//       _id: "phase-005",
//       name: "Deployment and Final Project",
//       description:
//         "دمج مكونات التطبيق ونشره في بيئة تجريبية، وتوثيق طريقة التشغيل، وتجهيز المشروع النهائي للمراجعة والتقييم.",

//       startDate: "2026-11-26",
//       deadline: "2026-12-30",
//       status: "PENDING",
//       progress: 0,

//       tasks: [
//         {
//           _id: "task-013",
//           title: "Configure Production Environment",
//           description:
//             "إعداد متغيرات البيئة وإعدادات النشر والتحقق من عدم تضمين الأسرار أو بيانات الاعتماد في المستودع.",

//           status: "PENDING",
//           priority: "HIGH",
//           createdAt: "2026-10-13",
//           deadline: "2026-12-05",
//           completedAt: null,

//           assignee: {
//             _id: "trainee-001",
//             fullname: "Ahmad Mohammad",
//             email: "ahmad.mohammad@syriatel.test",
//           },

//           notes: "يجب فصل إعدادات التطوير عن إعدادات الإنتاج.",
//         },
//         {
//           _id: "task-014",
//           title: "Deploy Full Stack Application",
//           description:
//             "نشر الواجهة والخادم وقاعدة البيانات في بيئة تجريبية، والتحقق من الاتصال بين الخدمات.",

//           status: "PENDING",
//           priority: "HIGH",
//           createdAt: "2026-10-13",
//           deadline: "2026-12-15",
//           completedAt: null,

//           assignee: {
//             _id: "trainee-002",
//             fullname: "Sara Khaled",
//             email: "sara.khaled@syriatel.test",
//           },

//           notes: "توثيق رابط التطبيق وخطوات النشر.",
//         },
//         {
//           _id: "task-015",
//           title: "Prepare Final Project Documentation",
//           description:
//             "كتابة توثيق المشروع، وشرح بنية النظام وواجهات API، وإعداد تعليمات التثبيت والتشغيل وعرض المشروع.",

//           status: "PENDING",
//           priority: "MEDIUM",
//           createdAt: "2026-10-13",
//           deadline: "2026-12-25",
//           completedAt: null,

//           assignee: {
//             _id: "trainee-003",
//             fullname: "Omar Ali",
//             email: "omar.ali@syriatel.test",
//           },

//           notes: "يجب أن يستطيع مطور آخر تشغيل المشروع باتباع التوثيق.",
//         },
//          {
//           _id: "task-015",
//           title: "Prepare Final Project Documentation",
//           description:
//             "كتابة توثيق المشروع، وشرح بنية النظام وواجهات API، وإعداد تعليمات التثبيت والتشغيل وعرض المشروع.",

//           status: "PENDING",
//           priority: "MEDIUM",
//           createdAt: "2026-10-13",
//           deadline: "2026-12-25",
//           completedAt: null,

//           assignee: {
//             _id: "trainee-003",
//             fullname: "Omar Ali",
//             email: "omar.ali@syriatel.test",
//           },

//           notes: "يجب أن يستطيع مطور آخر تشغيل المشروع باتباع التوثيق.",
//         },
//       ],
//     },
//   ],
// };
