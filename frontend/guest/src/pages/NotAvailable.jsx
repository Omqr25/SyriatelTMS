// import React from 'react'
// import styles from "./Application.module.css";
// import { CheckCircle2 } from 'lucide-react';
// import { Link } from 'react-router-dom';

// const NotAvailable = () => {
//   return (
//     <div>
//        <section className={styles.success}>
//         <div className={styles.successCard}>
//           <CheckCircle2 className={styles.successIcon} size={64} />

//           <h1>NotAvailable</h1>

//           <p>The requested form is not available at the moment.</p>

//           <Link to="/" className={styles.homeButton}>
//             Home
//           </Link>
//         </div>
//       </section>
//     </div>
//   )
// }

// export default NotAvailable


import React from "react";
import styles from "./Application.module.css";
import { CalendarClock } from "lucide-react";
import { Link } from "react-router-dom";

const NotAvailable = () => {
  return (
    <section className={styles.success}>
      <div className={styles.successCard}>
        <CalendarClock
          className={styles.successIcon}
          size={64}
        />

        <h1>Registration Is Currently Closed</h1>

        <p>
          Registration for the training program has ended for the current
          training cycle.
        </p>

        <p>
          Please wait for the next training cycle to open and submit your
          application then.
        </p>

        <Link to="/" className={styles.homeButton}>
          Back to Home
        </Link>
      </div>
    </section>
  );
};

export default NotAvailable;

