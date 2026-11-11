import { Link } from "react-router-dom";

export default function Forbidden() {
  return (
    <main>
      <h1>403 - Forbidden</h1>
      <p>ليس لديك صلاحية للوصول إلى هذه الصفحة.</p>

      <Link to="/">
        العودة إلى الصفحة الرئيسية
      </Link>
    </main>
  );
}