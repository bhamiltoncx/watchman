/* Copyright 2013-present Facebook, Inc.
 * Licensed under the Apache License, Version 2.0 */

#include "watchman.h"
#include "thirdparty/wildmatch/wildmatch.h"

#ifndef FNM_CASEFOLD
# define FNM_CASEFOLD 0
# define NO_CASELESS_FNMATCH 1
#endif

struct match_data {
  char *pattern;
  bool caseless;
  bool wholename;
  bool noescape;
  bool pathname;
  bool period;
  bool wildmatch;
};

static bool eval_match(struct w_query_ctx *ctx,
    struct watchman_file *file,
    void *data)
{
  struct match_data *match = data;
  w_string_t *str;

  if (match->wholename) {
    str = w_query_ctx_get_wholename(ctx);
  } else {
    str = file->name;
  }

  if (match->wildmatch) {
    return wildmatch(
      match->pattern,
      str->buf,
      (match->pathname ? WM_PATHNAME : 0) |
      (match->caseless ? WM_CASEFOLD : 0),
      0) == WM_MATCH;
  } else {
    return fnmatch(match->pattern,
        str->buf,
        (match->period ? FNM_PERIOD : 0) |
        (match->noescape ? FNM_NOESCAPE : 0) |
        (match->pathname ? FNM_PATHNAME : 0) |
        (match->caseless ? FNM_CASEFOLD : 0)) == 0;
  }
}

static void dispose_match(void *data)
{
  struct match_data *match = data;

  free(match->pattern);
  free(match);
}

static w_query_expr *match_parser_inner(w_query *query,
    json_t *term, bool caseless)
{
  const char *ignore, *pattern, *scope = "basename";
  const char *which = caseless ? "imatch" : "match";
  int noescape = 0;
  int pathname = 0;
  int period = 1;
  int wildmatch = 0;
  struct match_data *data;

  if (json_unpack(
        term,
        "[s,s,s,{s?b,s?b,s?b,s?b}]",
        &ignore,
        &pattern,
        &scope,
        "noescape",
        &noescape,
        "pathname",
        &pathname,
        "period",
        &period,
        "wildmatch",
        &wildmatch) != 0 &&
      json_unpack(term, "[s,s,s]", &ignore, &pattern, &scope) != 0 &&
      json_unpack(term, "[s,s]", &ignore, &pattern) != 0) {
    ignore_result(asprintf(&query->errmsg,
        "Expected [\"%s\", \"pattern\", \"scope\"?]",
        which));
    return NULL;
  }

#ifdef NO_CASELESS_FNMATCH
  if (!wildmatch && caseless) {
    asprintf(&query->errmsg,
        "imatch: Your system doesn't support FNM_CASEFOLD");
    return NULL;
  }
#endif

  if (strcmp(scope, "basename") && strcmp(scope, "wholename")) {
    ignore_result(asprintf(&query->errmsg,
        "Invalid scope '%s' for %s expression",
        scope, which));
    return NULL;
  }

  data = malloc(sizeof(*data));
  data->pattern = strdup(pattern);
  data->caseless = caseless;
  data->wholename = !strcmp(scope, "wholename");
  data->noescape = noescape;
  data->pathname = pathname;
  data->period = period;
  data->wildmatch = wildmatch;

  return w_query_expr_new(eval_match, dispose_match, data);
}

static w_query_expr *match_parser(w_query *query, json_t *term)
{
  return match_parser_inner(query, term, !query->case_sensitive);
}
W_TERM_PARSER("match", match_parser)

static w_query_expr *imatch_parser(w_query *query, json_t *term)
{
  return match_parser_inner(query, term, true);
}
W_TERM_PARSER("imatch", imatch_parser)

/* vim:ts=2:sw=2:et:
 */
