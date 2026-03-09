export type Workspace = {
  id: string;
  name: string;
  slug: string;
  timezone: string;
  currency: string;
};

export type DashboardOverviewResponse = {
  data: {
    metrics: {
      total_spend: number;
      total_results: number;
      cpa_cpl: number;
      ctr: number;
      cpm: number;
      frequency: number;
    };
    comparison: Record<string, number>;
    best_campaign: {
      name: string;
      spend: number;
      results: number;
      efficiency: number | null;
    } | null;
    worst_campaign: {
      name: string;
      spend: number;
      results: number;
      efficiency: number | null;
    } | null;
    active_alerts: number;
    recent_recommendations: Array<{
      id: string;
      summary: string;
      priority: string;
      status: string;
      generated_at: string;
    }>;
    sync_freshness: {
      last_synced_at: string | null;
    };
  };
};
