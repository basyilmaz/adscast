export type Workspace = {
  id: string;
  name: string;
  slug: string;
  timezone: string;
  currency: string;
};

export type DashboardOverviewResponse = {
  data: {
    range: {
      start_date: string;
      end_date: string;
      comparison_start_date: string;
      comparison_end_date: string;
    };
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
    trend: Array<{
      date: string;
      spend: number;
      results: number;
    }>;
    workspace_health: {
      summary: string;
      active_accounts: number;
      total_accounts: number;
      active_campaigns: number;
      campaigns_requiring_attention: number;
      open_alerts: number;
      open_recommendations: number;
      last_synced_at: string | null;
    };
    account_health: Array<{
      id: string;
      account_id: string;
      name: string;
      status: string;
      currency: string | null;
      active_campaigns: number;
      open_alerts: number;
      spend: number;
      results: number;
      health_status: "healthy" | "warning" | "critical" | "idle";
      health_summary: string;
      last_synced_at: string | null;
    }>;
    urgent_actions: Array<{
      id: string;
      source: "alert" | "recommendation";
      priority: "high" | "medium" | "low" | string;
      title: string;
      detail: string | null;
      entity_type: string;
      entity_label: string;
      context_label: string | null;
      detected_at: string | null;
    }>;
    active_campaigns: Array<{
      id: string;
      name: string;
      account_name: string;
      account_external_id: string | null;
      objective: string | null;
      status: string;
      spend: number;
      results: number;
      ctr: number;
      cpm: number;
      frequency: number;
      open_alerts: number;
      health_status: "healthy" | "warning" | "idle" | string;
      health_summary: string;
    }>;
  };
};
