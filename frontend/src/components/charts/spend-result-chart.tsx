"use client";

import {
  CartesianGrid,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";

type Point = {
  date: string;
  spend: number;
  results: number;
};

export function SpendResultChart({ data }: { data: Point[] }) {
  return (
    <div className="h-[280px] w-full">
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={data}>
          <CartesianGrid strokeDasharray="3 3" stroke="#d8ddcf" />
          <XAxis dataKey="date" tick={{ fontSize: 12 }} />
          <YAxis yAxisId="left" tick={{ fontSize: 12 }} />
          <YAxis yAxisId="right" orientation="right" tick={{ fontSize: 12 }} />
          <Tooltip />
          <Line
            yAxisId="left"
            type="monotone"
            dataKey="spend"
            stroke="#c1532b"
            strokeWidth={2}
            dot={false}
            name="Harcama"
          />
          <Line
            yAxisId="right"
            type="monotone"
            dataKey="results"
            stroke="#2f6f5e"
            strokeWidth={2}
            dot={false}
            name="Sonuc"
          />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}
