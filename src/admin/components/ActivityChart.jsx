import {
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { t } from "../lib/i18n";

/** Conversations per day. Lives in its own file so the chart library is only loaded when the Dashboard shows it. */
export default function ActivityChart({ daily }) {
  return (
    <ResponsiveContainer width="100%" height="100%">
      <BarChart
        data={daily.map((d) => ({
          ...d,
          conversations: +d.conversations,
          booking_clicks: +d.booking_clicks,
        }))}>
        <CartesianGrid vertical={false} strokeDasharray="3 3" />
        <XAxis dataKey="day" tickLine={false} axisLine={false} fontSize={12} />
        <YAxis
          allowDecimals={false}
          tickLine={false}
          axisLine={false}
          fontSize={12}
          width={30}
        />
        <Tooltip />
        <Bar
          dataKey="conversations"
          name={t("Conversations")}
          fill="hsl(var(--chart-1))"
          radius={[4, 4, 0, 0]}
        />
        <Bar
          dataKey="booking_clicks"
          name={t("Booking clicks")}
          fill="hsl(var(--chart-2))"
          radius={[4, 4, 0, 0]}
        />
      </BarChart>
    </ResponsiveContainer>
  );
}
