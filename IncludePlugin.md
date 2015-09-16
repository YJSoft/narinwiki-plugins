# 소개 #

  * Dokuwiki의 include 플러그인 http://www.dokuwiki.org/plugin:include 의 나린위키 포팅입니다.
  * 다른 위키 문서를 현재 문서로 '포함' 하는 플러그인
  * 특정 섹션만 '포함' 가능
  * ~~무한 루핑 등을 피하기 위해서 대상 문서는 cache 되어 있어야~~
  * nocontainer 설정시 목차와 주석을 업데이트
  * 실험기능: random=wiki\_box : 해당 문서의 wiki\_box 중 임의로 포함
  * 2012-01-08 update: partialnocache : 나린위키 2012-01-01 버전의 '부분 nocache' 기능 구현

# 플러그인 설정 #
  * ![https://narinwiki-plugins.googlecode.com/hg/include_setting.png](https://narinwiki-plugins.googlecode.com/hg/include_setting.png)

# 간단한 문법 #
  * `{{page=/경로/문서}}`: /경로/문서 문서 전체를 포함
  * `{{page=/경로/문서#문단}}}`: /경로/문서 의 '문단' section 만 포함

# 자세한 문법 및사용예 #
  * ![https://narinwiki-plugins.googlecode.com/hg/include_example.png](https://narinwiki-plugins.googlecode.com/hg/include_example.png)