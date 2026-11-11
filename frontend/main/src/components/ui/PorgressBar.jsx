import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Cell,
  ResponsiveContainer
} from "recharts";

import styles from "./ProgressBar.module.css";

function ProgressBar({
  value = 0,
  height = 50
}) {
  const progress = Math.min(100, Math.max(0, Number(value) || 0));

  const data = [
    {
      name: "Progress",
      completed: progress,
      remaining: 100 - progress
    }
  ];

  return (
    <div className={styles.progress}>
      <div className={styles["progress-track"]} style={{ height }}>
        <ResponsiveContainer width="100%" height="100%" >
          <BarChart
            data={data}
            layout="vertical"
            margin={{ top: 0, right: 0, bottom: 0, left: 0 }}
          >
            <XAxis type="number" domain={[0, 100]} hide />
            <YAxis type="category" dataKey="name" hide />
            <Bar

              dataKey="completed"
              stackId="progress"
              radius={[8, 0, 0, 8]}
              isAnimationActive={true}
            >
              <Cell
              fill={"var(--red-300)"} />
            </Bar>

            <Bar
              dataKey="remaining"
              stackId="progress"
              radius={[0, 8, 8, 0]}
              isAnimationActive={true}
            >
              <Cell fill={"var(--ink-300)"} />
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>

      <span className={styles["progress-value"]}>
        {Math.round(progress)}%
      </span>
    </div>
  );
}

export default ProgressBar;

