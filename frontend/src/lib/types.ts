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
    delivery_profile: ReportDeliveryProfileListItem | null;
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
    delivery_profile: ReportDeliveryProfileListItem | null;
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

export type ReportTemplateListItem = {
  id: string;
  name: string;
  entity_type: "account" | "campaign" | string;
  entity_id: string;
  entity_label: string | null;
  context_label: string | null;
  report_type: string;
  default_range_days: number;
  layout_preset: string;
  notes: string | null;
  is_active: boolean;
  delivery_schedules_count: number;
  created_at: string | null;
  report_url: string;
};

export type ReportDeliveryRunListItem = {
  id: string;
  status: string;
  trigger_mode: string;
  prepared_at: string | null;
  delivered_at: string | null;
  snapshot_title: string | null;
  snapshot_url: string | null;
  can_retry: boolean;
  retry_of_run_id: string | null;
  retried_by_run_id: string | null;
  contact_tags: string[];
  tagged_contacts: ReportContactListItem[];
  tagged_contacts_count: number;
  resolved_recipients: string[];
  resolved_recipients_count: number;
  share_link: {
    id: string;
    label: string | null;
    status: "active" | "revoked" | "expired" | string;
    expires_at: string | null;
    share_url: string | null;
    export_csv_url: string | null;
  } | null;
  delivery: {
    channel: string;
    channel_label: string;
    mailer: string | null;
    recipients: string[];
    recipients_count: number;
    share_link_used: boolean;
    outbound: boolean;
  } | null;
  schedule: {
    id: string;
    cadence: string;
    cadence_label: string;
    delivery_channel: string;
    delivery_channel_label: string;
    is_active: boolean;
    next_run_at: string | null;
    template: {
      id: string | null;
      name: string | null;
      entity_type: string | null;
      entity_id: string | null;
      entity_label: string | null;
      context_label: string | null;
      report_url: string | null;
    };
  } | null;
  error_message: string | null;
};

export type ReportShareLinkListItem = {
  id: string;
  label: string | null;
  status: "active" | "revoked" | "expired" | string;
  allow_csv_download: boolean;
  expires_at: string | null;
  revoked_at: string | null;
  last_accessed_at: string | null;
  access_count: number;
  created_at: string | null;
  share_url: string | null;
  export_csv_url: string | null;
};

export type ReportDeliveryScheduleListItem = {
  id: string;
  delivery_channel: string;
  delivery_channel_label: string;
  cadence: string;
  cadence_label: string;
  send_time: string;
  timezone: string;
  weekday: number | null;
  month_day: number | null;
  recipients: string[];
  recipients_count: number;
  recipient_preset_id: string | null;
  recipient_preset_name: string | null;
  contact_tags: string[];
  tagged_contacts: ReportContactListItem[];
  tagged_contacts_count: number;
  resolved_recipients: string[];
  resolved_recipients_count: number;
  recipient_group_summary: {
    mode: string;
    label: string;
    preset_name: string | null;
    contact_tags: string[];
    static_recipients_count: number;
    manual_recipients_count: number;
    preset_recipients_count: number;
    dynamic_contacts_count: number;
    resolved_recipients_count: number;
    sample_contact_names: string[];
  };
  share_delivery: {
    enabled: boolean;
    label_template: string | null;
    expires_in_days: number | null;
    allow_csv_download: boolean;
  };
  is_active: boolean;
  next_run_at: string | null;
  last_run_at: string | null;
  last_status: string | null;
  last_report_snapshot_id: string | null;
  last_report_snapshot_title: string | null;
  last_report_snapshot_url: string | null;
  created_at: string | null;
  template: {
    id: string | null;
    name: string | null;
    entity_type: string | null;
    entity_id: string | null;
    entity_label: string | null;
    context_label: string | null;
    report_type: string | null;
    report_url: string | null;
  };
  recent_runs: ReportDeliveryRunListItem[];
};

export type ReportRecipientPresetListItem = {
  id: string;
  name: string;
  recipients: string[];
  recipients_count: number;
  contact_tags: string[];
  tagged_contacts: ReportContactListItem[];
  tagged_contacts_count: number;
  resolved_recipients: string[];
  resolved_recipients_count: number;
  recipient_group_summary: {
    mode: string;
    label: string;
    preset_name: string | null;
    contact_tags: string[];
    static_recipients_count: number;
    manual_recipients_count: number;
    preset_recipients_count: number;
    dynamic_contacts_count: number;
    resolved_recipients_count: number;
    sample_contact_names: string[];
  };
  notes: string | null;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
};

export type ReportContactListItem = {
  id: string;
  name: string;
  email: string;
  company_name: string | null;
  role_label: string | null;
  tags: string[];
  notes: string | null;
  is_primary: boolean;
  is_active: boolean;
  last_used_at: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type ReportContactSegmentListItem = {
  tag: string;
  contacts_count: number;
  active_contacts_count: number;
  primary_contacts_count: number;
  companies_count: number;
  companies: string[];
  sample_contacts: Array<{
    id: string;
    name: string;
    email: string;
    is_primary: boolean;
    last_used_at: string | null;
  }>;
  last_used_at: string | null;
};

export type ReportDeliveryProfileListItem = {
  id: string;
  entity_type: "account" | "campaign" | string;
  entity_id: string;
  entity_label: string | null;
  context_label: string | null;
  report_url: string | null;
  recipient_preset_id: string | null;
  recipient_preset_name: string | null;
  delivery_channel: string;
  delivery_channel_label: string;
  cadence: string;
  cadence_label: string;
  send_time: string;
  timezone: string;
  weekday: number | null;
  month_day: number | null;
  default_range_days: number;
  layout_preset: string;
  recipients: string[];
  recipients_count: number;
  contact_tags: string[];
  tagged_contacts: ReportContactListItem[];
  tagged_contacts_count: number;
  resolved_recipients: string[];
  resolved_recipients_count: number;
  recipient_group_summary: {
    mode: string;
    label: string;
    preset_name: string | null;
    contact_tags: string[];
    static_recipients_count: number;
    manual_recipients_count: number;
    preset_recipients_count: number;
    dynamic_contacts_count: number;
    resolved_recipients_count: number;
    sample_contact_names: string[];
  };
  share_delivery: {
    enabled: boolean;
    label_template: string | null;
    expires_in_days: number | null;
    allow_csv_download: boolean;
  };
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
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
    share_links?: ReportShareLinkListItem[];
  };
  share_link?: {
    id: string;
    label: string | null;
    expires_at: string | null;
    allow_csv_download: boolean;
    access_count: number;
    export_csv_url: string | null;
  };
};

export type ReportIndexResponse = {
  data: {
    summary: {
      total_snapshots: number;
      account_snapshots: number;
      campaign_snapshots: number;
    };
    template_summary: {
      total_templates: number;
      active_templates: number;
      templates_with_schedules: number;
    };
    contact_summary: {
      total_contacts: number;
      active_contacts: number;
      primary_contacts: number;
      tagged_contacts: number;
    };
    contact_segment_summary: {
      total_segments: number;
      segments_with_primary_contact: number;
      segments_used_recently: number;
    };
    recipient_preset_summary: {
      total_presets: number;
      active_presets: number;
      total_recipients: number;
      segment_backed_presets: number;
    };
    delivery_profile_summary: {
      total_profiles: number;
      active_profiles: number;
    };
    delivery_summary: {
      total_schedules: number;
      active_schedules: number;
      due_schedules: number;
      runs_last_7_days: number;
    };
    delivery_capabilities: {
      default_mailer: string;
      real_email_available: boolean;
      from_address: string | null;
      from_name: string | null;
      note: string;
    };
    delivery_run_summary: {
      total_runs: number;
      failed_runs: number;
      delivered_runs: number;
      retryable_runs: number;
      latest_failed_at: string | null;
    };
    share_summary: {
      total_links: number;
      active_links: number;
      expiring_soon: number;
    };
    items: ReportSnapshotListItem[];
    templates: ReportTemplateListItem[];
    contacts: ReportContactListItem[];
    contact_segments: ReportContactSegmentListItem[];
    recipient_presets: ReportRecipientPresetListItem[];
    delivery_profiles: ReportDeliveryProfileListItem[];
    delivery_runs: ReportDeliveryRunListItem[];
    delivery_schedules: ReportDeliveryScheduleListItem[];
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
