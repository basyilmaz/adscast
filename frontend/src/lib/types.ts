export type Workspace = {
  id: string;
  name: string;
  slug: string;
  timezone: string;
  currency: string;
};

export type NextBestActionItem = {
  id: string;
  source: "alert" | "recommendation" | string;
  priority: "high" | "medium" | "low" | "critical" | string;
  title: string;
  entity_type: "workspace" | "account" | "campaign" | "ad_set" | "ad" | string;
  entity_label: string | null;
  context_label: string | null;
  route: string | null;
  why_it_matters: string | null;
  recommended_action: string | null;
  detected_at?: string | null;
  generated_at?: string | null;
};

export type AlertFeedItem = {
  id: string;
  code: string;
  severity: string;
  status: string;
  summary: string;
  explanation: string | null;
  confidence: number | null;
  date_detected: string | null;
  entity_type: "workspace" | "account" | "campaign" | "ad_set" | "ad" | string;
  entity_label: string | null;
  context_label: string | null;
  route: string | null;
  why_it_matters: string;
  impact_summary: string;
  recommended_action: string | null;
  next_step: string;
};

export type RecommendationFeedItem = {
  id: string;
  summary: string;
  details: string | null;
  priority: string;
  status: string;
  source: string;
  action_type: string | null;
  generated_at: string | null;
  entity_type: "workspace" | "account" | "campaign" | "ad_set" | "ad" | string;
  entity_label: string | null;
  context_label: string | null;
  route: string | null;
  operator_view: {
    summary: string;
    budget_note: string | null;
    creative_note: string | null;
    targeting_note: string | null;
    landing_page_note: string | null;
    next_test: string | null;
  };
  client_view: {
    headline: string;
    summary: string;
  };
  action_status: {
    code: string;
    label: string;
    manual_review_required: boolean;
  };
};

export type AlertEntityGroup = {
  entity_type: string;
  count: number;
  critical_count: number;
  items: AlertFeedItem[];
};

export type RecommendationEntityGroup = {
  entity_type: string;
  count: number;
  high_priority_count: number;
  items: RecommendationFeedItem[];
};

export type AdAccountListResponse = {
  data: {
    data: Array<{
      id: string;
      account_id: string;
      name: string;
      currency: string | null;
      timezone_name: string | null;
      status: string;
      is_active: boolean;
      last_synced_at: string | null;
      sync_status: "fresh" | "stale" | "lagging" | "unknown" | string;
      active_campaigns: number;
      total_campaigns: number;
      open_alerts: number;
      open_recommendations: number;
      spend: number;
      results: number;
      ctr: number;
      cpm: number;
      frequency: number;
      health_status: "healthy" | "warning" | "critical" | "idle" | string;
      health_summary: string;
    }>;
    summary: {
      total_accounts: number;
      active_accounts: number;
      restricted_accounts: number;
      accounts_requiring_attention: number;
      total_spend: number;
      total_results: number;
      open_alerts: number;
    };
    range: {
      start_date: string;
      end_date: string;
    };
  };
};

export type AdAccountDetailResponse = {
  data: {
    range: {
      start_date: string;
      end_date: string;
    };
    ad_account: {
      id: string;
      account_id: string;
      name: string;
      currency: string | null;
      timezone_name: string | null;
      status: string;
      is_active: boolean;
      last_synced_at: string | null;
    };
    summary: {
      spend: number;
      results: number;
      cpa_cpl: number | null;
      ctr: number;
      cpm: number;
      frequency: number;
      active_campaigns: number;
      total_campaigns: number;
      active_ad_sets: number;
      active_ads: number;
      open_alerts: number;
      open_recommendations: number;
    };
    health: {
      status: "healthy" | "warning" | "critical" | "idle" | string;
      summary: string;
      sync_status: "fresh" | "stale" | "lagging" | "unknown" | string;
    };
    trend: Array<{
      date: string;
      spend: number;
      results: number;
    }>;
    campaigns: Array<{
      id: string;
      meta_campaign_id: string | null;
      name: string;
      objective: string | null;
      status: string;
      effective_status: string | null;
      spend: number;
      results: number;
      cpa_cpl: number | null;
      ctr: number;
      cpm: number;
      frequency: number;
      open_alerts: number;
      open_recommendations: number;
      health_status: "healthy" | "warning" | "critical" | "idle" | string;
      health_summary: string;
      updated_at: string | null;
    }>;
    alerts: AlertFeedItem[];
    recommendations: RecommendationFeedItem[];
    next_best_actions: NextBestActionItem[];
    report_preview: {
      headline: string;
      client_summary: string;
      operator_summary: string;
      next_step: string;
    };
  };
};

export type CampaignDetailResponse = {
  data: {
    range: {
      start_date: string;
      end_date: string;
    };
    campaign: {
      id: string;
      name: string;
      meta_campaign_id: string;
      objective: string | null;
      status: string;
      effective_status: string | null;
      buying_type: string | null;
      daily_budget: number | null;
      lifetime_budget: number | null;
      start_time: string | null;
      stop_time: string | null;
      last_synced_at: string | null;
      ad_account: {
        id: string | null;
        name: string | null;
        account_id: string | null;
      };
    };
    health: {
      status: "healthy" | "warning" | "critical" | "idle" | string;
      summary: string;
    };
    summary: {
      spend: number;
      results: number;
      cpa_cpl: number | null;
      ctr: number;
      cpm: number;
      frequency: number;
      active_ad_sets: number;
      active_ads: number;
      open_alerts: number;
      open_recommendations: number;
    };
    trend: Array<{
      date: string;
      spend: number;
      results: number;
      ctr: number;
      cpm: number;
      frequency: number;
    }>;
    ad_sets: Array<{
      id: string;
      meta_ad_set_id: string | null;
      name: string;
      status: string;
      effective_status: string | null;
      optimization_goal: string | null;
      billing_event: string | null;
      bid_strategy: string | null;
      daily_budget: number | null;
      lifetime_budget: number | null;
      start_time: string | null;
      stop_time: string | null;
      ads_count: number;
      active_ads: number;
      spend: number | null;
      results: number | null;
      cpa_cpl: number | null;
      ctr: number | null;
      cpm: number | null;
      frequency: number | null;
      has_performance_data: boolean;
      performance_scope: "adset" | "campaign_only" | string;
      targeting_summary: {
        countries: string[];
        cities: string[];
        age_range: {
          min: number | null;
          max: number | null;
        };
        platforms: string[];
        interests: string[];
        custom_audiences: string[];
      };
      health_status: "healthy" | "warning" | "critical" | "idle" | string;
      health_summary: string;
      last_synced_at: string | null;
    }>;
    ads: Array<{
      id: string;
      meta_ad_id: string | null;
      name: string;
      status: string;
      effective_status: string | null;
      preview_url: string | null;
      spend: number | null;
      results: number | null;
      cpa_cpl: number | null;
      ctr: number | null;
      cpm: number | null;
      frequency: number | null;
      has_performance_data: boolean;
      performance_scope: "ad" | "campaign_only" | string;
      ad_set: {
        id: string | null;
        name: string | null;
      };
      creative: {
        id: string | null;
        name: string | null;
        asset_type: string | null;
        headline: string | null;
        body: string | null;
        description: string | null;
        call_to_action: string | null;
        destination_url: string | null;
      };
      health_status: "healthy" | "warning" | "critical" | "idle" | string;
      health_summary: string;
      last_synced_at: string | null;
    }>;
    alerts: AlertFeedItem[];
    recommendations: RecommendationFeedItem[];
    next_best_actions: NextBestActionItem[];
    analysis: {
      biggest_risk: string | null;
      biggest_opportunity: string | null;
      operator_note: string | null;
      client_note: string | null;
    };
    report_preview: {
      headline: string;
      client_summary: string;
      operator_summary: string;
      next_test: string;
      next_step: string;
    };
  };
};

export type AdSetDetailResponse = {
  data: {
    range: {
      start_date: string;
      end_date: string;
    };
    ad_set: {
      id: string;
      meta_ad_set_id: string;
      name: string;
      status: string;
      effective_status: string | null;
      optimization_goal: string | null;
      billing_event: string | null;
      bid_strategy: string | null;
      daily_budget: number | null;
      lifetime_budget: number | null;
      start_time: string | null;
      stop_time: string | null;
      last_synced_at: string | null;
      campaign: {
        id: string | null;
        name: string | null;
        objective: string | null;
      };
      ad_account: {
        id: string | null;
        name: string | null;
        account_id: string | null;
      };
    };
    summary: {
      spend: number | null;
      results: number | null;
      cpa_cpl: number | null;
      ctr: number | null;
      cpm: number | null;
      frequency: number | null;
      active_ads: number;
      total_ads: number;
      has_performance_data: boolean;
      performance_scope: "adset" | "campaign_only" | string;
    };
    trend: Array<{
      date: string;
      spend: number;
      results: number;
      ctr: number;
      cpm: number;
      frequency: number;
    }>;
    targeting_summary: {
      countries: string[];
      cities: string[];
      age_range: {
        min: number | null;
        max: number | null;
      };
      platforms: string[];
      interests: string[];
      custom_audiences: string[];
    };
    sibling_ad_sets: Array<{
      id: string;
      name: string;
      status: string;
      optimization_goal: string | null;
      daily_budget: number | null;
      spend: number | null;
      results: number | null;
      has_performance_data: boolean;
      targeting_summary: {
        countries: string[];
        cities: string[];
        age_range: {
          min: number | null;
          max: number | null;
        };
        platforms: string[];
        interests: string[];
        custom_audiences: string[];
      };
    }>;
    ads: Array<{
      id: string;
      name: string;
      status: string;
      effective_status: string | null;
      preview_url: string | null;
      spend: number | null;
      results: number | null;
      has_performance_data: boolean;
      creative: {
        asset_type: string | null;
        headline: string | null;
        call_to_action: string | null;
      };
    }>;
    inherited_alerts: Array<{
      id: string;
      severity: string;
      summary: string;
      recommended_action: string | null;
      date_detected: string | null;
    }>;
    inherited_recommendations: Array<{
      id: string;
      priority: string;
      summary: string;
      details: string | null;
      generated_at: string | null;
    }>;
    guidance: {
      operator_summary: string;
      next_test: string;
      data_scope_note: string;
      targeting_note: string;
    };
  };
};

export type AdDetailResponse = {
  data: {
    range: {
      start_date: string;
      end_date: string;
    };
    ad: {
      id: string;
      meta_ad_id: string;
      name: string;
      status: string;
      effective_status: string | null;
      preview_url: string | null;
      last_synced_at: string | null;
      campaign: {
        id: string | null;
        name: string | null;
        objective: string | null;
      };
      ad_set: {
        id: string | null;
        name: string | null;
      };
      ad_account: {
        id: string | null;
        name: string | null;
        account_id: string | null;
      };
    };
    summary: {
      spend: number | null;
      results: number | null;
      cpa_cpl: number | null;
      ctr: number | null;
      cpm: number | null;
      frequency: number | null;
      has_preview: boolean;
      performance_scope: "ad" | "campaign_only" | string;
    };
    trend: Array<{
      date: string;
      spend: number;
      results: number;
      ctr: number;
      cpm: number;
      frequency: number;
    }>;
    creative: {
      id: string | null;
      name: string | null;
      asset_type: string | null;
      headline: string | null;
      body: string | null;
      description: string | null;
      call_to_action: string | null;
      destination_url: string | null;
    };
    sibling_ads: Array<{
      id: string;
      name: string;
      status: string;
      preview_url: string | null;
      spend: number | null;
      results: number | null;
      has_performance_data: boolean;
      creative: {
        headline: string | null;
        asset_type: string | null;
      };
    }>;
    inherited_alerts: Array<{
      id: string;
      severity: string;
      summary: string;
      recommended_action: string | null;
      date_detected: string | null;
    }>;
    inherited_recommendations: Array<{
      id: string;
      priority: string;
      summary: string;
      details: string | null;
      generated_at: string | null;
    }>;
    guidance: {
      creative_note: string;
      operator_summary: string;
      data_scope_note: string;
      risk_note: string;
    };
  };
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
    next_best_actions: NextBestActionItem[];
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

export type AlertIndexResponse = {
  data: {
    data: AlertFeedItem[];
  };
  summary: {
    open_total: number;
    critical_total: number;
    entity_types: number;
    top_recommended_action: string | null;
  };
  entity_groups: AlertEntityGroup[];
  next_best_actions: NextBestActionItem[];
};

export type RecommendationIndexResponse = {
  data: {
    data: RecommendationFeedItem[];
  };
  summary: {
    open_total: number;
    high_priority_total: number;
    entity_types: number;
    manual_review_total: number;
  };
  entity_groups: RecommendationEntityGroup[];
  next_best_actions: NextBestActionItem[];
};

export type ReportSnapshotListItem = {
  id: string;
  title: string;
  entity_type: "account" | "campaign" | string;
  entity_id: string | null;
  entity_label: string | null;
  context_label: string | null;
  report_type: string;
  start_date: string | null;
  end_date: string | null;
  created_at: string | null;
  report_url: string | null;
  snapshot_url: string;
  export_csv_url: string;
};

export type ClientReportPayload = {
  range: {
    start_date: string;
    end_date: string;
  };
  entity: {
    type: "account" | "campaign" | string;
    id: string;
    name: string;
    external_id: string | null;
    context_label: string | null;
  };
  report: {
    type: string;
    title: string;
    headline: string;
    client_summary: string;
    operator_summary: string;
    biggest_risk: string | null;
    biggest_opportunity: string | null;
    next_test: string | null;
    next_step: string | null;
    generated_at: string;
  };
  summary: {
    spend: number;
    results: number;
    cpa_cpl: number | null;
    ctr?: number | null;
    cpm?: number | null;
    frequency?: number | null;
    active_campaigns?: number;
    total_campaigns?: number;
    active_ad_sets?: number;
    active_ads?: number;
    open_alerts: number;
    open_recommendations: number;
  };
  trend: Array<{
    date: string;
    spend: number;
    results: number;
  }>;
  focus_areas: Array<{
    label: string;
    detail: string | null;
  }>;
  what_we_tested: Array<{
    type: string;
    title: string;
    subtitle: string;
    status: string;
    note: string;
    route: string | null;
  }>;
  risks: AlertFeedItem[];
  recommendations: RecommendationFeedItem[];
  next_best_actions: NextBestActionItem[];
  snapshot_defaults: {
    report_type: string;
  };
  export_options: {
    live_csv_url?: string;
    pdf_foundation: {
      supported: boolean;
      mode: string;
      note: string;
    };
  };
  snapshot_history?: Array<{
    id: string;
    title: string;
    start_date: string | null;
    end_date: string | null;
    created_at: string | null;
    snapshot_url: string;
    export_csv_url: string;
  }>;
  snapshot?: {
    id: string;
    report_type: string;
    created_at: string | null;
    export_csv_url: string;
  };
};

export type ReportIndexResponse = {
  data: {
    summary: {
      total_snapshots: number;
      account_snapshots: number;
      campaign_snapshots: number;
    };
    items: ReportSnapshotListItem[];
    builders: {
      accounts: Array<{
        id: string;
        name: string;
        external_id: string | null;
        status: string;
        route: string;
      }>;
      campaigns: Array<{
        id: string;
        name: string;
        objective: string | null;
        status: string;
        context_label: string | null;
        route: string;
      }>;
    };
  };
};

export type ClientReportResponse = {
  data: ClientReportPayload;
};
