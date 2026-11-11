
import {
  PieChart as RechartsPieChart,
  Pie,
  Cell,
  Legend,
  ResponsiveContainer
} from "recharts";

import styles from  "./PieChart.module.css";


function PieChart({
  data = {},
  color = ["var(--red-300)","var(--ink-300)"],
  innerRadius = 60,
  outerRadius = 85,
  showLabels = false,
  centerLabel = ""
}) {

let totalData = [data , {name:"",value:100-data.value , notice:""}];


return (
    <section className={styles["pie-chart"]}>
    {data.name && (
        <h3 className={styles['pie-chart-title']}>{data.name}</h3>
    )}
        <div className={styles["pie-chart-container"]}>
  <div className={styles["pie-chart-visual"]}>
    <ResponsiveContainer width="100%" height="100%">
      <RechartsPieChart onClick={(e) => e.preventDefault()}>
        <Pie
          onClick={(e) => e.preventDefault()}
          data={totalData}
          dataKey="value"
          nameKey="name"
          cx="50%"
          cy="50%"
          innerRadius={innerRadius}
          outerRadius={outerRadius}
          paddingAngle={2}
          label={showLabels}
          isAnimationActive={true}
        >
          {totalData.map((entry, index) => (
            <Cell
          onClick={(e) => e.preventDefault()}

              key={`${entry.name}-${index}`}
              fill={color[index % color.length]}
            />
          ))}
        </Pie>

        <text
          x="50%"
          y="47%"
          textAnchor="middle"
          dominantBaseline="middle"
          className={styles["pie-chart__percentage"]}
        >
          {`${Math.round(data.value)}%`}
        </text>

        {centerLabel && (
          <text
            x="50%"
            y="57%"
            textAnchor="middle"
            dominantBaseline="middle"
            className={styles["pie-chart__center-label"]}
          >
            {centerLabel}
          </text>
        )}
      </RechartsPieChart>
    </ResponsiveContainer>
  </div>

  {data.notice && (
    <p className={styles["pie-chart-notice"]}>
      {data.notice}
    </p>
  )}
</div>
    </section>
);
}

export default PieChart;