<?php

/**
 * Render the body of an application email by building it up section-by-section.
 *
 * @task compose  Composition
 * @task render   Rendering
 */
final class PhabricatorMetaMTAMailBody extends Phobject {

  private $sections = array();
  private $htmlSections = array();
  private $attachments = array();

  private $viewer;

  public function getViewer() {
    return $this->viewer;
  }

  public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

/* -(  Composition  )-------------------------------------------------------- */


  /**
   * Add a raw block of text to the email. This will be rendered as-is.
   *
   * @param string Block of text.
   * @return this
   * @task compose
   */
  public function addRawSection($text) {
    if (strlen($text)) {
      $text = rtrim($text);
      $this->sections[] = $text;
      $this->htmlSections[] = phutil_escape_html_newlines(
        phutil_tag('div', array(), $text));
    }
    return $this;
  }

  public function addRemarkupSection($header, $text) {
    try {
      $engine = PhabricatorMarkupEngine::newMarkupEngine(array());
      $engine->setConfig('viewer', $this->getViewer());
      $engine->setMode(PhutilRemarkupEngine::MODE_TEXT);
      $styled_text = $engine->markupText($text);
      $this->addPlaintextSection($header, $styled_text);
    } catch (Exception $ex) {
      phlog($ex);
      $this->addTextSection($header, $text);
    }

    try {
      $mail_engine = PhabricatorMarkupEngine::newMarkupEngine(array());
      $mail_engine->setConfig('viewer', $this->getViewer());
      $mail_engine->setMode(PhutilRemarkupEngine::MODE_HTML_MAIL);
      $mail_engine->setConfig(
        'uri.base',
        PhabricatorEnv::getProductionURI('/'));
      $html = $mail_engine->markupText($text);
      $this->addHTMLSection($header, $html);
    } catch (Exception $ex) {
      phlog($ex);
      $this->addHTMLSection($header, $text);
    }

    return $this;
  }

  public function addRawPlaintextSection($text) {
    if (strlen($text)) {
      $text = rtrim($text);
      $this->sections[] = $text;
    }
    return $this;
  }

  public function addRawHTMLSection($html) {
    $this->htmlSections[] = phutil_safe_html($html);
    return $this;
  }


  /**
   * Add a block of text with a section header. This is rendered like this:
   *
   *    HEADER
   *      Text is indented.
   *
   * @param string Header text.
   * @param string Section text.
   * @return this
   * @task compose
   */
  public function addTextSection($header, $section) {
    if ($section instanceof PhabricatorMetaMTAMailSection) {
      $plaintext = $section->getPlaintext();
      $html = $section->getHTML();
    } else {
      $plaintext = $section;
      $html = phutil_escape_html_newlines(phutil_tag('div', array(), $section));
    }

    $this->addPlaintextSection($header, $plaintext);
    $this->addHTMLSection($header, $html);
    return $this;
  }

  public function addPlaintextSection($header, $text) {
    $this->sections[] = $header."\n".$this->indent($text);
    return $this;
  }

  public function addHTMLSection($header, $html_fragment) {
    if ($header !== null) {
      $header = phutil_tag('strong', array(), $header);
    }

    $this->htmlSections[] = array(
      phutil_tag(
        'div',
        array(),
        array(
          $header,
          phutil_tag('div', array(), $html_fragment),
        )),
    );
    return $this;
  }

  public function addLinkSection($header, $link) {
    $html = phutil_tag('a', array('href' => $link), $link);
    $this->addPlaintextSection($header, $link);
    $this->addHTMLSection($header, $html);
    return $this;
  }

  /**
   * Add a Herald section with a rule management URI and a transcript URI.
   *
   * @param string URI to rule transcripts.
   * @return this
   * @task compose
   */
  public function addHeraldSection($xscript_uri) {
    if (!PhabricatorEnv::getEnvConfig('metamta.herald.show-hints')) {
      return $this;
    }

    $this->addLinkSection(
      pht('WHY DID I GET THIS EMAIL?'),
      PhabricatorEnv::getProductionURI($xscript_uri));

    return $this;
  }

  /**
   * Add an attachment.
   *
   * @param PhabricatorMetaMTAAttachment Attachment.
   * @return this
   * @task compose
   */
  public function addAttachment(PhabricatorMetaMTAAttachment $attachment) {
    $this->attachments[] = $attachment;
    return $this;
  }


/* -(  Rendering  )---------------------------------------------------------- */


  /**
   * Render the email body.
   *
   * @return string Rendered body.
   * @task render
   */
  public function render() {
    $header = $this->getHtmlHead();
    $footer = $this->getHtmlFooter();
    $bodyHTML = implode("\n\n", $this->sections)."\n";
    return $header."\n".$bodyHTML."\n".$footer;
  }

  public function renderHTML() {
    $header = $this->getHtmlHead();
    $footer = $this->getHtmlFooter();
    $br = phutil_tag('br');
    $body = phutil_implode_html($br, $this->htmlSections);
    $bodyHTML = (string)hsprintf('%s', array($body, $br));
    return $header."\n".$bodyHTML."\n".$footer;
  }

  /**
   * Retrieve attachments.
   *
   * @return list<PhabricatorMetaMTAAttachment> Attachments.
   * @task render
   */
  public function getAttachments() {
    return $this->attachments;
  }


  /**
   * Indent a block of text for rendering under a section heading.
   *
   * @param string Text to indent.
   * @return string Indented text.
   * @task render
   */
  private function indent($text) {
    return rtrim("  ".str_replace("\n", "\n  ", $text));
  }

  private function getHtmlHead(){
    return base64_decode("PCFET0NUWVBFIGh0bWw+DQo8aHRtbCBsYW5nPSJlbiI+DQo8aGVhZD4NCiAgPG1ldGEgY2hhcnNldD0idXRmLTgiPiA8IS0tIHV0Zi04IHdvcmtzIGZvciBtb3N0IGNhc2VzIC0tPg0KICA8bWV0YSBuYW1lPSJ2aWV3cG9ydCIgY29udGVudD0id2lkdGg9ZGV2aWNlLXdpZHRoIj4gPCEtLSBGb3JjaW5nIGluaXRpYWwtc2NhbGUgc2hvdWxkbid0IGJlIG5lY2Vzc2FyeSAtLT4NCiAgPG1ldGEgaHR0cC1lcXVpdj0iWC1VQS1Db21wYXRpYmxlIiBjb250ZW50PSJJRT1lZGdlIj4gPCEtLSBVc2UgdGhlIGxhdGVzdCAoZWRnZSkgdmVyc2lvbiBvZiBJRSByZW5kZXJpbmcgZW5naW5lIC0tPg0KICA8dGl0bGU+PC90aXRsZT4gPCEtLSB0aGUgPHRpdGxlPiB0YWcgc2hvd3Mgb24gZW1haWwgbm90aWZpY2F0aW9ucyBvbiBBbmRyb2lkIDQuNC4gLS0+DQogIDxzdHlsZSB0eXBlPSJ0ZXh0L2NzcyI+DQogICAgLyogZW5zdXJlIHRoYXQgY2xpZW50cyBkb24ndCBhZGQgYW55IHBhZGRpbmcgb3Igc3BhY2VzIGFyb3VuZCB0aGUgZW1haWwgZGVzaWduIGFuZCBhbGxvdyB1cyB0byBzdHlsZSBlbWFpbHMgZm9yIHRoZSBlbnRpcmUgd2lkdGggb2YgdGhlIHByZXZpZXcgcGFuZSAqLw0KICAgIGJvZHksDQogICAgI2JvZHlUYWJsZSB7DQogICAgICBoZWlnaHQ6MTAwJSAhaW1wb3J0YW50Ow0KICAgICAgd2lkdGg6MTAwJSAhaW1wb3J0YW50Ow0KICAgICAgbWFyZ2luOjA7DQogICAgICBwYWRkaW5nOjA7DQogICAgfQ0KICAgIC8qIEVuc3VyZXMgV2Via2l0LSBhbmQgV2luZG93cy1iYXNlZCBjbGllbnRzIGRvbid0IGF1dG9tYXRpY2FsbHkgcmVzaXplIHRoZSBlbWFpbCB0ZXh0LiAqLw0KICAgIGJvZHksDQogICAgdGFibGUsDQogICAgdGQsDQogICAgcCwNCiAgICBhLA0KICAgIGxpLA0KICAgIGJsb2NrcXVvdGUgew0KICAgICAgLW1zLXRleHQtc2l6ZS1hZGp1c3Q6MTAwJTsNCiAgICAgIC13ZWJraXQtdGV4dC1zaXplLWFkanVzdDoxMDAlOw0KICAgIH0NCiAgICAvKiBGb3JjZXMgWWFob28hIHRvIGRpc3BsYXkgZW1haWxzIGF0IGZ1bGwgd2lkdGggKi8NCiAgICAudGhyZWFkLWl0ZW0uZXhwYW5kZWQgLnRocmVhZC1ib2R5IC5ib2R5LA0KICAgIC5tc2ctYm9keSB7DQogICAgICB3aWR0aDogMTAwJSAhaW1wb3J0YW50Ow0KICAgICAgZGlzcGxheTogYmxvY2sgIWltcG9ydGFudDsNCiAgICB9DQogICAgLyogRm9yY2VzIEhvdG1haWwgdG8gZGlzcGxheSBlbWFpbHMgYXQgZnVsbCB3aWR0aCAqLw0KICAgIC5SZWFkTXNnQm9keSwNCiAgICAuRXh0ZXJuYWxDbGFzcyB7DQogICAgICB3aWR0aDogMTAwJTsNCiAgICAgIGJhY2tncm91bmQtY29sb3I6ICNmNGY0ZjQ7DQogICAgfQ0KICAgIC8qIEZvcmNlcyBIb3RtYWlsIHRvIGRpc3BsYXkgbm9ybWFsIGxpbmUgc3BhY2luZy4gKi8NCiAgICAuRXh0ZXJuYWxDbGFzcywNCiAgICAuRXh0ZXJuYWxDbGFzcyBwLA0KICAgIC5FeHRlcm5hbENsYXNzIHNwYW4sDQogICAgLkV4dGVybmFsQ2xhc3MgZm9udCwNCiAgICAuRXh0ZXJuYWxDbGFzcyB0ZCwNCiAgICAuRXh0ZXJuYWxDbGFzcyBkaXYgew0KICAgICAgbGluZS1oZWlnaHQ6MTAwJTsNCiAgICB9DQogICAgLyogUmVzb2x2ZXMgd2Via2l0IHBhZGRpbmcgaXNzdWUuICovDQogICAgdGFibGUgew0KICAgICAgYm9yZGVyLXNwYWNpbmc6MDsNCiAgICB9DQogICAgLyogUmVzb2x2ZXMgdGhlIE91dGxvb2sgMjAwNywgMjAxMCwgYW5kIEdtYWlsIHRkIHBhZGRpbmcgaXNzdWUsIGFuZCByZW1vdmVzIHNwYWNpbmcgYXJvdW5kIHRhYmxlcyB0aGF0IE91dGxvb2sgYWRkcy4gKi8NCiAgICB0YWJsZSwNCiAgICB0ZCB7DQogICAgICBib3JkZXItY29sbGFwc2U6Y29sbGFwc2U7DQogICAgICBtc28tdGFibGUtbHNwYWNlOjBwdDsNCiAgICAgIG1zby10YWJsZS1yc3BhY2U6MHB0Ow0KICAgIH0NCiAgICAvKiBDb3JyZWN0cyB0aGUgd2F5IEludGVybmV0IEV4cGxvcmVyIHJlbmRlcnMgcmVzaXplZCBpbWFnZXMgaW4gZW1haWxzLiAqLw0KICAgIGltZyB7DQogICAgICAtbXMtaW50ZXJwb2xhdGlvbi1tb2RlOiBiaWN1YmljOw0KICAgIH0NCiAgICAvKiBFbnN1cmVzIGltYWdlcyBkb24ndCBoYXZlIGJvcmRlcnMgb3IgdGV4dC1kZWNvcmF0aW9ucyBhcHBsaWVkIHRvIHRoZW0gYnkgZGVmYXVsdC4gKi8NCiAgICBpbWcsDQogICAgYSBpbWcgew0KICAgICAgYm9yZGVyOjA7DQogICAgICBvdXRsaW5lOm5vbmU7DQogICAgICB0ZXh0LWRlY29yYXRpb246bm9uZTsgICAgIA0KICAgIH0NCiAgICAvKiBTdHlsZXMgWWFob28ncyBhdXRvLXNlbnNpbmcgbGluayBjb2xvciBhbmQgYm9yZGVyICovDQogICAgLnlzaG9ydGN1dHMgYSB7DQogICAgICBib3JkZXItYm90dG9tOiBub25lICFpbXBvcnRhbnQ7DQogICAgfQ0KICAgIC8qIFN0eWxlcyB0aGUgdGVsIFVSTCBzY2hlbWUgKi8NCiAgICBhW2hyZWZePXRlbF0sDQogICAgLm1vYmlsZV9saW5rLA0KICAgIC5tb2JpbGVfbGluayBhIHsNCiAgICAgIGNvbG9yOiMyMjIyMjIgIWltcG9ydGFudDsNCiAgICAgIHRleHQtZGVjb3JhdGlvbjogdW5kZXJsaW5lICFJbXBvcnRhbnQ7DQogICAgfQ0KICAgIC5yZWNlaXB0LXRleHQgew0KICAgICAgZm9udC1mYW1pbHk6IHNhbnMtc2VyaWY7DQogICAgICBmb250LXNpemU6IDE0cHg7DQogICAgICBsaW5lLWhlaWdodDogMjFweDsNCiAgICB9DQogICAgLm1lc3NhZ2UtdGV4dCB7DQogICAgICBmb250LWZhbWlseTogc2Fucy1zZXJpZjsNCiAgICAgIGZvbnQtc2l6ZTogMTJweDsNCiAgICAgIGxpbmUtaGVpZ2h0OiAxN3B4Ow0KICAgIH0NCiAgICAuYmlsbC10ZXh0IHsNCiAgICAgIGZvbnQtZmFtaWx5OiBzYW5zLXNlcmlmOw0KICAgICAgZm9udC1zaXplOiAxNHB4Ow0KICAgICAgbGluZS1oZWlnaHQ6IDMwcHg7DQogICAgfQ0KICAgIC5oZWFkaW5nLXRleHQgew0KICAgICAgZm9udC1mYW1pbHk6IHNhbnMtc2VyaWY7DQogICAgICBmb250LXNpemU6IDE1cHg7DQogICAgICBsaW5lLWhlaWdodDogMjRweDsNCiAgICAgIHRleHQtdHJhbnNmb3JtOiB1cHBlcmNhc2U7DQogICAgfQ0KICAgIC5zbWFsbGVyLXRleHQgew0KICAgICAgZm9udC1mYW1pbHk6IHNhbnMtc2VyaWY7DQogICAgICBmb250LXNpemU6IDEycHg7DQogICAgICBsaW5lLWhlaWdodDogMTdweDsNCiAgICB9DQogICAgLmZvb3Rlci10ZXh0IHsNCiAgICAgIGZvbnQtZmFtaWx5OiBzYW5zLXNlcmlmOw0KICAgICAgZm9udC1zaXplOiAxMnB4Ow0KICAgICAgbGluZS1oZWlnaHQ6IDE4cHg7DQogICAgfQ0KICAgIC56ZGFyayB7DQogICAgICBjb2xvcjogIzJkMmQyYTsNCiAgICB9DQogICAgLnpkaGw1IHsNCiAgICAgIGNvbG9yOiAjN2Q3ZDc2Ow0KICAgIH0NCiAgICAuYm9yZGVyLWJvdHRvbSB7DQogICAgICBib3JkZXItYm90dG9tOiAxcHQgc29saWQgI2NiY2JjODsNCiAgICB9DQogICAgLnBib3QyMCB7DQogICAgICBwYWRkaW5nLWJvdHRvbTogMjBweDsNCiAgICB9DQogICAgLnBib3QxNSB7DQogICAgICBwYWRkaW5nLWJvdHRvbTogMTVweDsNCiAgICB9DQogICAgLnBib3QxMCB7DQogICAgICBwYWRkaW5nLWJvdHRvbTogMTBweDsNCiAgICB9DQogICAgLnB0b3AyMCB7DQogICAgICBwYWRkaW5nLXRvcDogMjBweDsNCiAgICB9DQogICAgLnB0b3A1IHsNCiAgICAgIHBhZGRpbmctdG9wOiA1cHg7DQogICAgfQ0KICAgIC5wbGVmdDEwIHsNCiAgICAgIHBhZGRpbmctbGVmdDogMTBweDsNCiAgICB9DQogICAgLnB0b3AxMCB7DQogICAgICBwYWRkaW5nLXRvcDogMTBweDsNCiAgICB9DQogICAgLnB0b3AxNSB7DQogICAgICBwYWRkaW5nLXRvcDogMTVweDsgDQogICAgfQ0KICAgIC5wcmlnaHQxMCB7DQogICAgICBwYWRkaW5nLXJpZ2h0OiAxMHB4Ow0KICAgIH0NCiAgICAucGJvdDEwIHsNCiAgICAgIHBhZGRpbmctYm90dG9tOiAxMHB4Ow0KICAgIH0NCiAgICAucGw0MCB7DQogICAgICBwYWRkaW5nLWxlZnQ6IDQwcHg7DQogICAgfSAgICANCiAgICAucHJpZ2h0NDAgew0KICAgICAgcGFkZGluZy1yaWdodDogNDBweDsNCiAgICB9DQogICAgLnRhLWxlZnQgew0KICAgICAgdGV4dC1hbGlnbjogbGVmdDsNCiAgICB9DQogICAgLnRhLXJpZ2h0IHsNCiAgICAgIHRleHQtYWxpZ246IHJpZ2h0OyANCiAgICB9DQogICAgLnRhLWNlbnRlciB7DQogICAgICB0ZXh0LWFsaWduOiBjZW50ZXI7DQogICAgfQ0KICAgIC5tdG9wMjUgew0KICAgICAgbWFyZ2luLXRvcDogMjVweDsNCiAgICB9DQogICAgLmJvbGQgew0KICAgICAgZm9udC13ZWlnaHQ6IGJvbGQ7DQogICAgfQ0KICAgIC8qIEFwcGxlIE1haWwgZG9lc24ndCBzdXBwb3J0IG1heC13aWR0aCwgc28gd2UgdXNlIG1lZGlhIHF1ZXJpZXMgdG8gY29uc3RyYWluIHRoZSBlbWFpbCBjb250YWluZXIgd2lkdGguICovDQogICAgQG1lZGlhIG9ubHkgc2NyZWVuIGFuZCAobWluLXdpZHRoOiA2MDFweCkgew0KICAgICAgLmVtYWlsLWNvbnRhaW5lciB7DQogICAgICAgIHdpZHRoOiA2MDBweCAhaW1wb3J0YW50Ow0KICAgICAgfQ0KICAgIH0gICAgICAgIA0KICA8L3N0eWxlPg0KPC9oZWFkPg0KPGJvZHkgbGVmdG1hcmdpbj0iMCIgdG9wbWFyZ2luPSIwIiBtYXJnaW53aWR0aD0iMCIgbWFyZ2luaGVpZ2h0PSIwIiBiZ2NvbG9yPSIjZjRmNGY0IiBzdHlsZT0ibWFyZ2luOiAwO3BhZGRpbmc6IDA7LXdlYmtpdC10ZXh0LXNpemUtYWRqdXN0OiBub25lOy1tcy10ZXh0LXNpemUtYWRqdXN0OiBub25lO2hlaWdodDogMTAwJSAhaW1wb3J0YW50O3dpZHRoOiAxMDAlICFpbXBvcnRhbnQ7Ij4NCjx0YWJsZSBjZWxscGFkZGluZz0iMCIgY2VsbHNwYWNpbmc9IjAiIGJvcmRlcj0iMCIgaGVpZ2h0PSIxMDAlIiB3aWR0aD0iMTAwJSIgYmFja2dyb3VuZD0iIiBpZD0iYm9keVRhYmxlIiBzdHlsZT0iYm9yZGVyLWNvbGxhcHNlOiBjb2xsYXBzZTt0YWJsZS1sYXlvdXQ6IGZpeGVkO21hcmdpbjogMCBhdXRvOy1tcy10ZXh0LXNpemUtYWRqdXN0OiAxMDAlOy13ZWJraXQtdGV4dC1zaXplLWFkanVzdDogMTAwJTtib3JkZXItc3BhY2luZzogMDttc28tdGFibGUtbHNwYWNlOiAwcHQ7bXNvLXRhYmxlLXJzcGFjZTogMHB0O3BhZGRpbmc6IDA7aGVpZ2h0OiAxMDAlICFpbXBvcnRhbnQ7d2lkdGg6IDEwMCUgIWltcG9ydGFudDsiPjx0cj48dGQgc3R5bGU9Ii1tcy10ZXh0LXNpemUtYWRqdXN0OiAxMDAlOy13ZWJraXQtdGV4dC1zaXplLWFkanVzdDogMTAwJTtib3JkZXItY29sbGFwc2U6IGNvbGxhcHNlO21zby10YWJsZS1sc3BhY2U6IDBwdDttc28tdGFibGUtcnNwYWNlOiAwcHQ7IGJhY2tncm91bmQ6ICNmNmY2ZjY7IHBhZGRpbmctdG9wOiAxNXB4O3BhZGRpbmctYm90dG9tOiAxNXB4OyI+DQogIDwhLS0gSGlkZGVuIFByZWhlYWRlciBUZXh0IDogQkVHSU4gLS0+DQogIDxkaXYgc3R5bGU9ImRpc3BsYXk6bm9uZTsgdmlzaWJpbGl0eTpoaWRkZW47IG9wYWNpdHk6MDsgY29sb3I6dHJhbnNwYXJlbnQ7IGhlaWdodDowOyB3aWR0aDowO2xpbmUtaGVpZ2h0OjA7IG92ZXJmbG93OmhpZGRlbjttc28taGlkZTogYWxsOyI+DQogIDwvZGl2Pg0KICA8IS0tIEhpZGRlbiBQcmVoZWFkZXIgVGV4dCA6IEVORCAtLT4NCiAgPCEtLSBPdXRsb29rIGFuZCBMb3R1cyBOb3RlcyBkb24ndCBzdXBwb3J0IG1heC13aWR0aCBidXQgYXJlIGFsd2F5cyBvbiBkZXNrdG9wLCBzbyB3ZSBjYW4gZW5mb3JjZSBhIHdpZGUsIGZpeGVkIHdpZHRoIHZpZXcuIC0tPg0KICA8IS0tIEJlZ2lubmluZyBvZiBPdXRsb29rLXNwZWNpZmljIHdyYXBwZXIgOiBCRUdJTiAtLT4NCiAgPCEtLVtpZiAoZ3RlIG1zbyA5KXwoSUUpXT4NCiAgPHRhYmxlIHdpZHRoPSI2MDAiIGFsaWduPSJjZW50ZXIiIGNlbGxwYWRkaW5nPSIwIiBjZWxsc3BhY2luZz0iMCIgYm9yZGVyPSIwIj4NCiAgICA8dHI+DQogICAgICA8dGQ+DQogIDwhW2VuZGlmXS0tPg0KICA8IS0tIEJlZ2lubmluZyBvZiBPdXRsb29rLXNwZWNpZmljIHdyYXBwZXIgOiBFTkQgLS0+DQogIDwhLS0gRW1haWwgd3JhcHBlciA6IEJFR0lOIC0tPg0KICA8dGFibGUgYm9yZGVyPSIwIiB3aWR0aD0iMTAwJSIgY2VsbHBhZGRpbmc9IjAiIGNlbGxzcGFjaW5nPSIwIiBhbGlnbj0iY2VudGVyIiBzdHlsZT0ibWF4LXdpZHRoOiA2MDBweDttYXJnaW46IGF1dG87LW1zLXRleHQtc2l6ZS1hZGp1c3Q6IDEwMCU7LXdlYmtpdC10ZXh0LXNpemUtYWRqdXN0OiAxMDAlO2JvcmRlci1zcGFjaW5nOiAwO2JvcmRlci1jb2xsYXBzZTogY29sbGFwc2U7bXNvLXRhYmxlLWxzcGFjZTogMHB0O21zby10YWJsZS1yc3BhY2U6IDBwdDsgYmFja2dyb3VuZDogd2hpdGUiIGNsYXNzPSJlbWFpbC1jb250YWluZXIiPg0KICAgIDx0cj4NCiAgICAgIDx0ZCBzdHlsZT0idGV4dC1hbGlnbjogY2VudGVyO3ZlcnRpY2FsLWFsaWduOiB0b3A7Zm9udC1zaXplOiAwOy1tcy10ZXh0LXNpemUtYWRqdXN0OiAxMDAlOy13ZWJraXQtdGV4dC1zaXplLWFkanVzdDogMTAwJTtib3JkZXItY29sbGFwc2U6IGNvbGxhcHNlO21zby10YWJsZS1sc3BhY2U6IDBwdDttc28tdGFibGUtcnNwYWNlOiAwcHQ7cGFkZGluZy1sZWZ0OiAxNXB4O3BhZGRpbmctcmlnaHQ6IDE1cHg7Ij4NCiAgICAgICAgPCEtLSBFbXB0eSBzcGFjZSBhdCB0aGUgdG9wIDogQkVHSU4gLS0+DQogICAgICAgIDx0YWJsZSBib3JkZXI9IjAiIHdpZHRoPSIxMDAlIiBjZWxscGFkZGluZz0iMCIgY2VsbHNwYWNpbmc9IjAiIHN0eWxlPSItbXMtdGV4dC1zaXplLWFkanVzdDogMTAwJTstd2Via2l0LXRleHQtc2l6ZS1hZGp1c3Q6IDEwMCU7Ym9yZGVyLXNwYWNpbmc6IDA7Ym9yZGVyLWNvbGxhcHNlOiBjb2xsYXBzZTttc28tdGFibGUtbHNwYWNlOiAwcHQ7bXNvLXRhYmxlLXJzcGFjZTogMHB0OyI+DQogICAgICAgICAgPHRyPg0KICAgICAgICAgICAgPHRkIGhlaWdodD0iMTUiIHN0eWxlPSJmb250LXNpemU6IDA7bGluZS1oZWlnaHQ6IDA7LW1zLXRleHQtc2l6ZS1hZGp1c3Q6IDEwMCU7LXdlYmtpdC10ZXh0LXNpemUtYWRqdXN0OiAxMDAlO2JvcmRlci1jb2xsYXBzZTogY29sbGFwc2U7bXNvLXRhYmxlLWxzcGFjZTogMHB0O21zby10YWJsZS1yc3BhY2U6IDBwdDsiPiZuYnNwOzwvdGQ+DQogICAgICAgICAgPC90cj4NCiAgICAgICAgPC90YWJsZT4NCiAgICAgICAgPCEtLSBFbXB0eSBzcGFjZSBhdCB0aGUgdG9wIDogRU5EIC0tPiANCiAgICAgICAgPHRhYmxlIGJvcmRlcj0iMCIgd2lkdGg9IjEwMCUiIGNlbGxwYWRkaW5nPSIwIiBjZWxsc3BhY2luZz0iMCIgYmdjb2xvcj0iI2Y2ZjZmNiIgc3R5bGU9Ii1tcy10ZXh0LXNpemUtYWRqdXN0OiAxMDAlOy13ZWJraXQtdGV4dC1zaXplLWFkanVzdDogMTAwJTtib3JkZXItc3BhY2luZzogMDtib3JkZXItY29sbGFwc2U6IGNvbGxhcHNlO21zby10YWJsZS1sc3BhY2U6IDBwdDttc28tdGFibGUtcnNwYWNlOiAwcHQ7Ij4NCiAgICAgIA0KICAgICAgICAgIDwhLS0gTG9nbyBDZW50ZXJlZCArIEJhY2tncm91bmQgSW1hZ2UgOiBCRUdJTiAtLT4NCiAgICAgICAgICA8dHIgc3R5bGU9ImJhY2tncm91bmQtc2l6ZTogY292ZXI7IiA+DQogICAgICAgICAgICA8dGQgdmFsaWduPSJtaWRkbGUiIGFsaWduPSJjZW50ZXIiIHN0eWxlPSJwYWRkaW5nOiAzMHB4IDA7dGV4dC1hbGlnbjogY2VudGVyO2JhY2tncm91bmQtY29sb3I6IHdoaXRlOy1tcy10ZXh0LXNpemUtYWRqdXN0OiAxMDAlOy13ZWJraXQtdGV4dC1zaXplLWFkanVzdDogMTAwJTtib3JkZXItY29sbGFwc2U6IGNvbGxhcHNlO21zby10YWJsZS1sc3BhY2U6IDBwdDttc28tdGFibGUtcnNwYWNlOiAwcHQ7Ij4NCiAgICAgICAgICAgICAgPGltZyBzcmM9Imh0dHA6Ly9iLnptdGNkbi5jb20vaW1hZ2VzL2xvZ28vbWFpbGVyX2xvZ28ucG5nIiBhbHQ9ImFsdCB0ZXh0IiB3aWR0aD0iMjQwIiBhbGlnbj0iY2VudGVyIiBib3JkZXI9IjAiIHN0eWxlPSJtYXJnaW46IGF1dG87dGV4dC1hbGlnbjogY2VudGVyOy1tcy1pbnRlcnBvbGF0aW9uLW1vZGU6IGJpY3ViaWM7Ym9yZGVyOiAwO291dGxpbmU6IG5vbmU7dGV4dC1kZWNvcmF0aW9uOiBub25lOyI+DQogICAgICAgICAgICA8L3RkPg0KICAgICAgICAgIDwvdHI+DQogICAgICAgICAgPCEtLSBMb2dvIENlbnRlcmVkICsgQmFja2dyb3VuZCBJbWFnZSA6IEVORCAtLT4NCiAgICAgICAgICA8IS0tIExvZ28gQ2VudGVyZWQgKyBCYWNrZ3JvdW5kIEltYWdlIDogRU5EIC0tPg0KICAgICAgICAgIDx0cj4NCiAgICAgICAgICAgIDx0ZCB2YWxpZ249Im1pZGRsZSIgYWxpZ249ImNlbnRlciIgc3R5bGU9InBhZGRpbmctdG9wOjIwcHg7IHRleHQtYWxpZ246IGNlbnRlcjtiYWNrZ3JvdW5kLWNvbG9yOiB3aGl0ZTsgYm9yZGVyLXRvcDogMXB4IHNvbGlkICNlNmU2ZTY7ICI+DQogICAgICAgICAgICAgIDxzcGFuIHN0eWxlPSJmb250LXNpemU6MjRweDsgY29sb3I6IzJkMmQyZDsiPk5vdGlmaWNhdGlvbiBmcm9tIFBoYWJyaWNhdG9yITwvc3Bhbj4NCiAgICAgICAgICAgIDwvdGQ+DQogICAgICAgICAgPC90cj4NCiAgICAgICAgICA8dHI+DQogICAgICAgICAgICA8dGQgdmFsaWduPSJtaWRkbGUiIGFsaWduPSJsZWZ0IiBzdHlsZT0icGFkZGluZzogMTBweCAxNXB4IDIwcHggMTVweDt0ZXh0LWFsaWduOiBsZWZ0O2JhY2tncm91bmQtY29sb3I6IHdoaXRlOy1tcy10ZXh0LXNpemUtYWRqdXN0OiAxMDAlOy13ZWJraXQtdGV4dC1zaXplLWFkanVzdDogMTAwJTtib3JkZXItY29sbGFwc2U6IGNvbGxhcHNlO21zby10YWJsZS1sc3BhY2U6IDBwdDttc28tdGFibGUtcnNwYWNlOiAwcHQ7ICI+DQogICAgICAgICAgICAgIDxkaXYgc3R5bGU9ImZvbnQtc2l6ZTogMTVweDsgY29sb3I6IzZkNmQ2ZDsgZm9udC13ZWlnaHQ6bm9ybWFsOyI+");
  }

  private function getHtmlFooter(){
    return base64_decode("ICAgICAgICAgICAgICA8L2Rpdj4NCiAgICAgICAgICAgIDwvdGQ+ICAgICAgICAgICAgDQogICAgICAgICAgPC90cj4gICAgICAgIA0KICAgICAgICA8L3RhYmxlPg0KICAgICAgICA8L3RkPg0KICAgIDwvdHI+DQogICAgPCEtLSBGb290ZXIgOiBCRUdJTiAtLT4NCiAgICA8dHI+DQogICAgICA8dGQgY2xhc3M9InRhLWNlbnRlciBmb290ZXItdGV4dCB6ZGhsNSIgc3R5bGU9InBhZGRpbmctdG9wOiA0MHB4O3BhZGRpbmctbGVmdDogMTVweDtwYWRkaW5nLXJpZ2h0OiAxNXB4Oy1tcy10ZXh0LXNpemUtYWRqdXN0OiAxMDAlOy13ZWJraXQtdGV4dC1zaXplLWFkanVzdDogMTAwJTtib3JkZXItY29sbGFwc2U6IGNvbGxhcHNlO21zby10YWJsZS1sc3BhY2U6IDBwdDttc28tdGFibGUtcnNwYWNlOiAwcHQ7Zm9udC1mYW1pbHk6IHNhbnMtc2VyaWY7Zm9udC1zaXplOiAxMnB4O2xpbmUtaGVpZ2h0OiAxOHB4O2NvbG9yOiAjN2Q3ZDc2O3RleHQtYWxpZ246IGNlbnRlcjsiPg0KICAgICAgICBJbmZyYSBUZWFtIPCfmI4gJmJ1bGw7IFpvbWF0byBNZWRpYSBQdnQuIEx0ZC48YnI+PGJyPg0KICAgICAgPC90ZD4NCiAgICA8L3RyPg0KICAgIDwhLS0gRm9vdGVyIDogRU5EIC0tPiAgICAgIA0KICA8L3RhYmxlPg0KICA8IS0tIEVtYWlsIHdyYXBwZXIgOiBFTkQgLS0+DQogIDwhLS0gRW5kIG9mIE91dGxvb2stc3BlY2lmaWMgd3JhcHBlciA6IEJFR0lOIC0tPg0KICA8IS0tW2lmIChndGUgbXNvIDkpfChJRSldPg0KICAgICAgPC90ZD4NCiAgICA8L3RyPg0KICA8L3RhYmxlPg0KICA8IVtlbmRpZl0tLT4NCiAgPCEtLSBFbmQgb2YgT3V0bG9vay1zcGVjaWZpYyB3cmFwcGVyIDogRU5EIC0tPg0KPC9ib2R5Pg0KPC9odG1sPg==");
  }
}
